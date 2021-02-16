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
	public function article($args, $request) {
		$this->_outputLinkset($args, $request, 'article');
	}

	/**
	 * Publication boundary linkset handler
	 * @param $args array
	 * @param $request Request
	 */
	public function boundary($args, $request) {
		$this->_outputLinkset($args, $request, 'boundary');
	}

	/**
	 * Bibliographic metadata linkset handler
	 * @param $args array
	 * @param $request Request
	 */
	public function biblio($args, $request) {
		$this->_outputLinkset($args, $request, 'biblio');
	}

	/**
	 * Linkset handler
	 * @param $args array
	 * @param $request Request
	 * @param $mode string
	 */
	protected function _outputLinkset($args, $request, $mode) {
		$headers	= Array();
		$request = Application::getRequest();
		$articleDao = DAORegistry::getDAO('SubmissionDAO');
		$issueDao	= DAORegistry::getDAO('IssueDAO');
		$journal	= $request->getJournal();
		$article	= $articleDao->getById($args[0]);
		if (empty($article)) return false;
		$plugin     = PluginRegistry::getPlugin('generic', 'signpostingplugin');
		$articleId  = $article->getId();
		switch ($mode) {
			case 'article' : $anchor = $request->url(null, 'article', 'view', $articleId);
							 break;
			case 'boundary': if (!$plugin->checkBoundary($args[0])) return false;
							 $anchor = $request->url(null, 'article', 'download', Array($articleId, $args[0]));
							 break;
			case 'biblio'  : if (!$plugin->checkCitation($args[1])) return false;
							 $anchor = $request->url(null, 'sp-citation', 'serveCitation', $articleId, Array('format' => $args[1]));
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
	protected function _getAnchor($mode, $articleId, $plugin) {
		$output = Array();
		$request = Application::getRequest();
		switch ($mode) {
			case 'article' : $output = $request->url(null, 'article', 'view', $articleId);
							 break;
			case 'boundary': $output = $request->url(null, 'article', 'view', Array($articleId, $args[1]));
							 break;
			case 'biblio'  : $output = $request->url(null, 'sp-citation', $args[2], $articleId);
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
	protected function _buildLinksetBody($arrayLinks, $anchor) {
		$output = Array('linkset' => Array());
		$output['linkset']['anchor'] = $anchor;
		foreach ($arrayLinks as $link) {
			$tmpArray = Array();
            if(!array_key_exists($link['rel'], $output['linkset'])){
				$output['linkset'][$link['rel']] = Array();
            }
            $tmpArray['href'] = $link['value'];
			if (isset($link['type'])) {
				$tmpArray['type'] = $link['type'];
			}
            $output['linkset'][$link['rel']][] = $tmpArray;
		}
		return $output;
	}
}
