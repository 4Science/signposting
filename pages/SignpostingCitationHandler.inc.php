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

	protected $_citationFormats   = Array();
	
	/**
	 * Citation handler method
	 * @param $args array
	 * @param $request Request
	 */
	public function serveCitation($args, $request) {
		$citationFormats = $this->_getCitationFormats();
		$params = $request->getQueryArray();
		// 'format' existence already checked in 'SignpostingPlugin.inc.php'
		// 'dispatcher' method
		if(array_key_exists($params['format'], $citationFormats)){
			$this->_outputCitation($params['format'], $args, $request);
		}
	}

	/**
	 * Citation format handler
	 * @param $format string
	 * @param $args array
	 * @param $request Request
	 */
	protected function _outputCitation($format, $args, &$request) {
		$citationFormats = $this->_getCitationFormats();
		if (isset($citationFormats[$format])) {
			$templateMgr = TemplateManager::getManager();
			$request = Application::getRequest();
			$articleDao  = DAORegistry::getDAO('SubmissionDAO');
			$issueDao	 = DAORegistry::getDAO('IssueDAO');
			$journal	 = $request->getJournal();
			$article	 = $articleDao->getById($args[0]);
			if (empty($article)) return false;
			$issue	     = $issueDao->getBySubmissionId($article->getId());
			$plugin      = PluginRegistry::loadPlugin('generic', 'citationStyleLanguage');
			$templateMgr->assign('articleId' , $article->getId());
			$templateMgr->assign('articleUrl', $request->url(null, 'article', 'view', $article->getId()));
			$filename    = substr(preg_replace('/[^a-zA-Z0-9_.-]/', '', str_replace(' ', '-', $article->getLocalizedTitle())), 0, 60);
			$citationString = trim(strip_tags($plugin->getCitation($request, $article, $format, $issue)));
			$citationString = str_replace('\n', "\n", $citationString);
			header('Content-Disposition: attachment; filename="' . $filename . '.' . $format . '.' . $citationFormats[$format]['fileExtension'] . '"');
			header('Content-Type: '.$citationFormats[$format]['contentType']);
			//old implementation
			//echo trim(html_entity_decode(strip_tags($plugin->getCitation($request, $article, $format, $issue)), ENT_QUOTES, 'UTF-8'));
			echo $citationString;
		}
	}
	
	/**
	 * Get Citation formats from 'citationStyleLanguage' Plugin
	 * 
	 */
	protected function _getCitationFormats() {
		if(count($this->_citationFormats) < 1){
			// The DOI plugin is loaded to register the hook: 'CitationStyleLanguage::citation'
			// Used by 'citationStyleLanguage' Plugin
			$doi    = PluginRegistry::loadPlugin('pubIds' , 'doi');
			$plugin = PluginRegistry::loadPlugin('generic', 'citationStyleLanguage');
			if(!empty($plugin)){
				$citationStyles = $plugin->getEnabledCitationStyles();
				$citationDwn = $plugin->getEnabledCitationDownloads();
				$citationFormats = array_merge($citationStyles, $citationDwn);
				foreach($citationFormats as $citationFormat){
					if(array_key_exists('contentType', $citationFormat)){
						$fileExt = $citationFormat['fileExtension'];
						$cntType = $citationFormat['contentType'];
					}else{
						$fileExt = 'txt';
						$cntType = 'text/plain';
					}
					$this->_citationFormats[$citationFormat['id']] = Array(
						'fileExtension' => $fileExt, 
						'contentType'   => $cntType);
				}
			}
		}
		return $this->_citationFormats;
	}
}
