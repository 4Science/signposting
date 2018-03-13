<?php

/**
 * @file plugins/generic/signposting/pages/SignpostingLinksetHandler.inc.php
 *
 * Copyright (c) 2015-2017 University of Pittsburgh
 * Copyright (c) 2014-2017 Simon Fraser University Library
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 * 
 * Contributed by 4Science (http://www.4science.it). 
 * 
 * @class SignpostingLinksetHandler
 * @ingroup plugins_generic_signposting 
 *
 * @brief Signposting linkset handler
 */

import('classes.handler.Handler');

class SignpostingLinksetHandler extends Handler {

	/**
	 * Article linkset handler
	 * @param $args array
	 * @param $request Request
	 */
	function article($args, $request) {
		$this->_outputLinkset($args, $request, 'article');
	}

	/**
	 * Publication boundary linkset handler
	 * @param $args array
	 * @param $request Request
	 */
	function boundary($args, $request) {
		$this->_outputLinkset($args, $request, 'boundary');
	}

	/**
	 * Bibliographic metadata linkset handler
	 * @param $args array
	 * @param $request Request
	 */
	function biblio($args, $request) {
		$this->_outputLinkset($args, $request, 'biblio');
	}

	/**
	 * Linkset handler
	 * @param $args array
	 * @param $request Request
	 * @param $mode string
	 */
	function _outputLinkset($args, $request, $mode) {
		$headers	= Array();
		$articleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$issueDao	= DAORegistry::getDAO('IssueDAO');
		$journal	= $request->getJournal();
		$article	= $articleDao->getPublishedArticleByArticleId($args[0]);
		if (empty($article)) return false;
		$plugin     = PluginRegistry::getPlugin('generic', 'signpostingplugin');
		$articleId  = $article->getId();
		switch ($mode) {
			case 'article' : $anchor = Request::url(null, 'article', 'view', $articleId);
							 break;
			case 'boundary': if (!$plugin->checkBoundary($args[1])) return false;
							 $anchor = Request::url(null, 'article', 'download', Array($articleId, $args[1]));
							 break;
			case 'biblio'  : if (!$plugin->checkCitation($args[1])) return false;
							 $anchor = Request::url(null, 'sp-citation', $args[1], $articleId);
							 break;
		}
		$modeParams  =  $plugin->getModeParameters($mode);
		$plugin->buildHeaders(
							  $headers,
							  $modeParams['configVarName'],
							  $modeParams['patternMode'],
							  $journal,
							  $article
							 );
		$body = json_encode($this->_buildLinksetBody($headers, $anchor),
							JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		//header('Content-Disposition: attachment; filename="' . $article->getId() . '-linkset.json"');
		header('Content-Type: application/linkset+json');
		echo $body;
	}

	/**
	 * Return the linkset Anchor
	 * @param $mode string
	 * @param $articleId int
	 * @param $plugin object
	 * @return string
	 */
	function _getAnchor($mode, $articleId, $plugin) {
		$output = Array();
		switch ($mode) {
			case 'article' : $output = Request::url(null, 'article', 'view', $articleId);
							 break;
			case 'boundary': $output = Request::url(null, 'article', 'view', Array($articleId, $args[1]));
							 break;
			case 'biblio'  : $output = Request::url(null, 'sp-citation', $args[2], $articleId);
							 break;
		}
		return $output;
	}

	/**
	 * Builds the linkset body
	 * @param $arrayLinks Array
	 * @param $anchor string
	 * @return Array
	 */
	function _buildLinksetBody($arrayLinks, $anchor) {
		$output = Array();
		foreach ($arrayLinks as $link) {
			$linksetElement = Array(
									'href'   => $link['value'],
									'anchor' => $anchor,
									'rel'    => Array($link['rel'])
								   );
			if (isset($link['type'])) {
				$linksetElement['type'] = $link['type'];
			}
			$output[] = $linksetElement;
		}
		return $output;
	}
}
