<?php

/**
 * @defgroup plugins_generic_signposting
 */
 
/**
 * @file plugins/generic/signposting/index.php
 *
 * Copyright (c) 2015-2017 University of Pittsburgh
 * Copyright (c) 2014-2017 Simon Fraser University Library
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 * 
 * Contributed by 4Science (http://www.4science.it).
 *
 * @ingroup plugins_generic_signposting
 * @brief Wrapper for Signposting plugin.
 *
 */

require_once('SignpostingPlugin.inc.php');

return new SignpostingPlugin();

