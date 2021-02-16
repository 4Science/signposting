<?php

/**
 * @file plugins/generic/signposting/Signposting.inc.php
 *
 * Copyright (c) 2015-2017 University of Pittsburgh
 * Copyright (c) 2014-2017 Simon Fraser University Library
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Contributed by 4Science (http://www.4science.it). 
 *
 * @class SignpostingPlugin
 * @ingroup plugins_generic_signposting
 *
 * @brief Signposting plugin class
 */

// Linkset URL example:
// https://<hostname>/<journal-url/sp-linkset/article/11176
 
import('lib.pkp.classes.plugins.GenericPlugin');

define('SIGNPOSTING_MAX_LINKS', 10);

class SignpostingPlugin extends GenericPlugin {

	protected $_landingPageConfig = Array('author',
										  'bibliographic metadata',
										  'identifier',
										  'publication boundary');

	protected $_biblioConfig	  = Array('bibliographic metadata');

	protected $_boundariesConfig  = Array('publication boundary');
	
	protected $_citationFormats   = Array();

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 *  the plugin will not be registered.
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled($mainContextId)) {
			// Register callback for the dispatcher
			HookRegistry::register('LoadHandler', Array($this, 'dispatcher'));
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.signposting.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.signposting.description');
	}

	/**
	 * Hook callback: Operations dispatcher
	 * @param $page
	 * @param $op
	 * @param $sourceFile
	 */
	public function dispatcher($page, $op) {
		$request	= Application::getRequest();
		$articleDao = DAORegistry::getDAO('SubmissionDAO');
		$issueDao	= DAORegistry::getDAO('IssueDAO');
		$journal	= $request->getJournal();
		$args		= $request->getRequestedArgs();
		$params     = $request->getQueryArray();
		$returnTrue = false;
		$returnHead = false;
		$mode		= false;
		if ($op[0] == 'sp-linkset') {
			$this->import('pages/SignpostingLinksetHandler');
			define('HANDLER_CLASS', 'SignpostingLinksetHandler');
			$returnTrue = true;
			$mode	    = 'linkset';
		} elseif ($op[0] == 'sp-citation') {
			if(array_key_exists('format', $params)) {
				if ($this->checkCitation($params['format'])) {
					$this->import('pages/SignpostingCitationHandler');
					define('HANDLER_CLASS', 'SignpostingCitationHandler');
					$returnTrue = true;
					$mode	    = 'biblio';
				} else {
					return false;
				}
			} else {
				return false;
			}
		} elseif ($op[0] == 'article' && $op[1] == 'view' && count($args) < 2) {
			$mode = 'article';
			// Intercept HEAD call
			// NB: Pagespeed issue prevent headers to be displayed on HEAD call
			if($_SERVER['REQUEST_METHOD'] == 'HEAD'){
				$returnHead = true;
			}
		// Changed to include the PDF preview page
		// } elseif ($op[0] == 'article' && $op[1] == 'download') {
		} elseif (($op[0] == 'article' && $op[1] == 'download') || ($op[0] == 'article' && $op[1] == 'view' && count($args) < 3)) {
			if ($this->checkBoundary($args[0])) {
				$mode = 'boundary';
			} else {
				return false;
			}
		} else {
			return false;
		}
		$article = $articleDao->getById($args[0]);
		if (empty($article)) return false;

		$headers	= Array();
		$modeParams = $this->getModeParameters($mode);
		if (count($modeParams) > 0) {
			$configVarName = $modeParams['configVarName'];
			$patternMode   = $modeParams['patternMode'];

			$this->buildHeaders($headers,
								$configVarName,
								$patternMode,
								$journal,
								$article);
		}
		if (count($headers) > SIGNPOSTING_MAX_LINKS) {
			switch ($mode) {
				case 'article' : $params = $article->getId();
								 break;
				case 'boundary': $params = Array($article->getId(), $args[1]);
								 break;
				case 'biblio'  : $params = Array($article->getId(), $params['format']);
								 break;
			}
			$linksetUrl = $request->url(null,
									   'sp-linkset',
									   $mode,
									   $params);
			$headers   = Array();
			$headers[] = Array('value' => $linksetUrl,
							   'rel'   => 'linkset',
							   'type'  => 'application/linkset+json');
		}
		$this->_outputHeaders($headers);
		if ($returnTrue) {
			return true;
		} elseif ($returnHead) {
			return false;
		}
	}

	/**
	 * Returns the parameters for the headers choice
	 * @param $mode
	 * @return Array
	 */
	public function getModeParameters($mode) {
		$output = Array();
		switch ($mode) {
			case 'article' : $output = Array('configVarName' => '_landingPageConfig',
											 'patternMode'   => 'toItem');
							 break;
			case 'boundary': $output = Array('configVarName' => '_boundariesConfig',
											 'patternMode'   => 'toCollection');
							 break;
			case 'biblio'  : $output = Array('configVarName' => '_biblioConfig',
											 'patternMode'   => 'toCollection');
							 break;
		}
		return $output;
	}

	/**
	 * Check if the citation is available
	 * @param $citation
	 * @return bool
	 */
	public function checkCitation($citation) {
		$citationFormats = $this->_getCitationFormats();
		if (isset($citationFormats[$citation])) {
			return true;
		}
		return false;
	}

	/**
	 * Check if the boundary resource is available
	 * @param $galleyId
	 * @return bool
	 */
	public function checkBoundary($galleyId) {
		$articleDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$galley	 = $articleDao->getById($galleyId);
		if (!empty($galley)) {
			return true;
		}
		return false;
	}

	/**
	 * Builds the list of headers
	 * @param Array $headers
	 * @param string $configVarName
	 * @param string $patternMode
	 * @param object $journal
	 * @param object $article
	 */
	public function buildHeaders(&$headers, $configVarName, $patternMode, $journal, $article) {
		foreach ($this->$configVarName as $pattern) {
			switch ($pattern) {
				case 'author':
					 $this->_authorPattern($headers, $article);
					 break;
				case 'bibliographic metadata':
					 $this->_bibliographicMetadataPattern($headers,
														  $journal,
														  $article,
														  $patternMode);
					 break;
				case 'identifier':
					 $this->_identifierPattern($headers, $journal, $article);
					 break;
				case 'publication boundary':
					 $this->_publicationBoundary($headers,
												 $journal,
												 $article,
												 $patternMode);
					 break;
			}
		}
	}

	/**
	 * Signposting Author pattern implementation
	 * @param Array $headers
	 * @param object $article
	 */
	protected function _authorPattern(&$headers, $article) {
		foreach ($article->getAuthors() as $author) {
			$orcid = $author->getData('orcid');
			if (!empty($orcid)) {
				$headers[] = Array('value' => $orcid,
								   'rel'   => 'author');
			}
		}
	}

	/**
	 * Signposting Bibliographic Metadata pattern implementation
	 * @param Array $headers
	 * @param object $article
	 * @param string $mode
	 */
	protected function _bibliographicMetadataPattern(&$headers, $journal, $article, $mode) {
		$request = Application::getRequest();
		if ($mode == 'toItem') {
			$rel = 'describedby';
		    $citationFormats = $this->_getCitationFormats();
			foreach ($citationFormats as $format => $mimeType) {
				$link = $request->url(null, 'sp-citation', 'serveCitation', $article->getId(), Array('format' => $format));
				$headers[] = Array('value' => $link,
								   'rel'   => $rel,
								   'type'  => $mimeType['contentType']);
			}
			$pubIdPlugin = PluginRegistry::loadPlugin('pubIds', 'doi');
			$pubId = $pubIdPlugin->getPubId($article);
			if (!empty($pubId)) {
				$headers[] = Array('value' => $pubIdPlugin->getResolvingURL($journal->getId(), $pubId),
								   'rel'   => $rel,
								   'type'  => 'application/vnd.citationstyles.csl+json');
			}
		} else {
			$rel  = 'describes';
			$link = $request->url(null, 'article', 'view', $article->getId());
			$headers[] = Array('value' => $link,
							   'rel'   => $rel);
		}
	}

	/**
	 * Signposting Identifier pattern implementation
	 * @param Array $headers
	 * @param object $journal
	 * @param object $article
	 */
	protected function _identifierPattern(&$headers, $journal, $article) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
		foreach ($pubIdPlugins as $pubIdPlugin) {
			$pubId = $pubIdPlugin->getPubId($article);
			if (!empty($pubId)) {
				$headers[] = Array('value' => $pubIdPlugin->getResolvingURL($journal->getId(), $pubId),
								   'rel'   => 'cite-as');
			}
		}
	}

	/**
	 * Signposting Publication Boundary pattern implementation
	 * @param Array $headers
	 * @param object $journal
	 * @param object $article
	 * @param string $mode
	 */
	protected function _publicationBoundary(&$headers, $journal, $article, $mode) {
		$request = Application::getRequest();
		$articleId = $article->getBestArticleId($journal);
		if ($mode == 'toItem') {
			$rel = 'item';
			foreach ($article->getGalleys() as $galley) {
				$link = $request->url(null, 'article', 'download',
									 Array($article->getId(),
										   $galley->getBestGalleyId($journal)));
				$mimeType = $galley->getFileType();
				$headers[] = Array('value' => $link,
								   'rel'   => $rel,
								   'type'  => $mimeType);
			}
		} else {
			$rel  = 'collection';
			$link = $request->url(null, 'article', 'view', $article->getId());
			$headers[] = Array('value' => $link,
							   'rel'   => $rel);
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

	/**
	 * HTTP Headers: formatting and sending
	 * @param Array $headers
	 */
	protected function _outputHeaders($headers) {
		if(count($headers) > 0){
			$indentation  = '';
			$headerString = 'Link: ';
			$headerArray  = Array();
			foreach ($headers as $header) {
				$headerArray[] = '<' . $header['value'] . '>'
								 . $indentation . '; rel="' . $header['rel'] . '"'
								 . (isset($header['type'])?$indentation . '; type="' . $header['type'] . '"':'');
			}
			$headerString .= join(','.$indentation, $headerArray);
			header($headerString);
		}
	}
}
