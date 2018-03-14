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

import('lib.pkp.classes.plugins.GenericPlugin');

define('SIGNPOSTING_CITATION_FORMATS', 
	   serialize(Array(
					   'bibtex'   => 'application/x-bibtex',
					   'endNote'  => 'application/x-endnote-refer',
					   'proCite'  => 'application/x-research-info-systems',
					   'refWorks' => 'application/x-refworks',
					   'refMan'   => 'application/x-research-info-systems'
				 )
	   )
);

define('SIGNPOSTING_MAX_LINKS', 10);

class SignpostingPlugin extends GenericPlugin {

	protected $_landingPageConfig = Array('author',
										  'bibliographic metadata',
										  'identifier',
										  'publication boundary');

	protected $_biblioConfig	  = Array('bibliographic metadata');

	protected $_boundariesConfig  = Array('publication boundary');

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 *  the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled()) {
			// Register callback for the dispatcher
			HookRegistry::register('LoadHandler', Array(&$this, 'dispatcher'));
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.signposting.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.signposting.description');
	}

	/**
	 * Hook callback: Operations dispatcher
	 * @param $page
	 * @param $op
	 * @param $sourceFile
	 */
	function dispatcher($page, $op, $sourceFile = null) {
		$request	=& Application::getRequest();
		$articleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$issueDao	=& DAORegistry::getDAO('IssueDAO');
		$journal	= $request->getJournal();
		$args		= $request->getRequestedArgs();
		$returnTrue = false;
		$returnHead = false;
		$mode		= false;
		if ($op[0] == 'sp-linkset') {
			$this->import('pages/SignpostingLinksetHandler');
			define('HANDLER_CLASS', 'SignpostingLinksetHandler');
			$returnTrue = true;
			$mode		= 'linkset';
		} elseif ($op[0] == 'sp-citation') {
			if ($this->checkCitation($op[1])) {
				$this->import('pages/SignpostingCitationHandler');
				define('HANDLER_CLASS', 'SignpostingCitationHandler');
				$returnTrue = true;
				$mode		= 'biblio';
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
		} elseif ($op[0] == 'article' && $op[1] == 'download') {
			if ($this->checkBoundary($args[1])) {
				$mode = 'boundary';
			} else {
				return false;
			}
		} else {
			return false;
		}

		$article =& $articleDao->getPublishedArticleByArticleId($args[0]);
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
				case 'article' : $params = $article->getArticleId();
								 break;
				case 'boundary': $params = Array($article->getArticleId(), $args[1]);
								 break;
				case 'biblio'  : $params = Array($article->getArticleId(), $op[1]);
								 break;
			}
			$linksetUrl = Request::url(null,
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
	function getModeParameters($mode) {
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
	function checkCitation($citation) {
		$citationFormats = unserialize(SIGNPOSTING_CITATION_FORMATS);
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
	function checkBoundary($galleyId) {
		$articleDao =& DAORegistry::getDAO('ArticleGalleyDAO');
		$galley = $articleDao->getGalley($galleyId);
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
	function buildHeaders(&$headers, $configVarName, $patternMode, $journal, $article) {
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
	function _authorPattern(&$headers, $article) {
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
	function _bibliographicMetadataPattern(&$headers, $journal, $article, $mode) {
		if ($mode == 'toItem') {
			$rel = 'describedby';
			$citationFormats = unserialize(SIGNPOSTING_CITATION_FORMATS);
			foreach ($citationFormats as $format => $mimeType) {
				$link = Request::url(null, 'sp-citation', $format, $article->getArticleId());
				$headers[] = Array('value' => $link,
								   'rel'   => $rel,
								   'type'  => $mimeType);
			}
			$pubIdPlugin =& PluginRegistry::loadPlugin('pubIds', 'doi');
			$pubId = $pubIdPlugin->getPubId($article);
			if (!empty($pubId)) {
				$headers[] = Array('value' => $pubIdPlugin->getResolvingURL($journal->getId(), $pubId),
								   'rel'   => $rel,
								   'type'  => 'application/vnd.citationstyles.csl+json');
			}
		} else {
			$rel  = 'describes';
			$link = Request::url(null, 'article', 'view', $article->getArticleId());
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
	function _identifierPattern(&$headers, $journal, $article) {
		$pubIdPlugins =& PluginRegistry::loadCategory('pubIds', true);
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
	function _publicationBoundary(&$headers, $journal, $article, $mode) {
		$articleId = $article->getBestArticleId($journal);
		if ($mode == 'toItem') {
			$rel = 'item';
			foreach ($article->getGalleys() as $galley) {
				$link = Request::url(null, 'article', 'download',
									 Array($article->getArticleId(),
										   $galley->getBestGalleyId($journal)));
				$mimeType = $galley->getFileType();
				$headers[] = Array('value' => $link,
								   'rel'   => $rel,
								   'type'  => $mimeType);
			}
		} else {
			$rel  = 'collection';
			$link = Request::url(null, 'article', 'view', $article->getArticleId());
			$headers[] = Array('value' => $link,
							   'rel'   => $rel);
		}
	}

	/**
	 * HTTP Headers: formatting and sending
	 * @param Array $headers
	 */
	function _outputHeaders($headers) {
		if(count($headers) > 0){
			//$indentation  = '\n	  ';
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
