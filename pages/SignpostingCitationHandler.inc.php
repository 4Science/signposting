<?php

/**
 * @file plugins/generic/signposting/pages/SignpostingCitationHandler.inc.php
 *
 * Copyright (c) 2015-2017 University of Pittsburgh
 * Copyright (c) 2014-2017 Simon Fraser University Library
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 * 
 * Contributed by 4Science (http://www.4science.it). 
 * 
 * @class SignpostingCitationHandler
 * @ingroup plugins_generic_signposting 
 *
 * @brief Signposting citations handler
 */

import('classes.handler.Handler');

class SignpostingCitationHandler extends Handler {

	/**
	 * Bibtex citation handler
	 * @param $args array
	 * @param $request Request
	 */
	function bibtex($args, &$request) {
		$this->_outputCitation('bibtex', $args, $request);
	}

	/**
	 * EndNote citation handler
	 * @param $args array
	 * @param $request Request
	 */
	function endNote($args, &$request) {
		$this->_outputCitation('endNote', $args, $request);
	}

	/**
	 * ProCite citation handler
	 * @param $args array
	 * @param $request Request
	 */
	function proCite($args, &$request) {
		$this->_outputCitation('proCite', $args, $request);
	}

	/**
	 * RefWorks citation handler
	 * @param $args array
	 * @param $request Request
	 */
	function refWorks($args, &$request) {
		$this->_outputCitation('refWorks', $args, $request);
	}

	/**
	 * RefMan citation handler
	 * @param $args array
	 * @param $request Request
	 */
	function refMan($args, &$request) {
		$this->_outputCitation('refMan', $args, $request);
	}

	/**
	 * Citation format handler
	 * @param $format string
	 * @param $args array
	 * @param $request Request
	 */
	function _outputCitation($format, $args, &$request) {
		$citationFormats = unserialize(SIGNPOSTING_CITATION_FORMATS);
		if (isset($citationFormats[$format])) {
			$templateMgr =& TemplateManager::getManager();
			$articleDao  =& DAORegistry::getDAO('PublishedArticleDAO');
			$issueDao	 =& DAORegistry::getDAO('IssueDAO');
			$journal	 =& $request->getJournal();
			$article	 =& $articleDao->getPublishedArticleByArticleId($args[0]);
			if (empty($article)) return false;
			$issue =& $issueDao->getIssueByArticleId($article->getArticleId());
			$outputExceptions = Array('bibtex'   => 'bib',
									  'refWorks' => 'txt');
			$plugin =& PluginRegistry::loadPlugin('citationFormats', $format);
			$templateMgr->assign('articleId' , $article->getArticleId());
			$templateMgr->assign('articleUrl', Request::url(null, 'article', 'view', $article->getArticleId()));
			if (isset($outputExceptions[$format])) {
				header('Content-Disposition: attachment; filename="' . $article->getId() . '-'.$format.'.'.$outputExceptions[$format].'"');
				header('Content-Type: '.$citationFormats[$format]);
				echo html_entity_decode(strip_tags($plugin->fetchCitation($article, $issue, $journal)), ENT_QUOTES, 'UTF-8');
			} else {
				$plugin->displayCitation($article, $issue, $journal);
			}
		}
	}
}
