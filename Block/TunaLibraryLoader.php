<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\View\Element\Template;

/**
 * Block to load Tuna library globally as a script tag in page head
 * This ensures window.Tuna is available before any RequireJS modules
 */
class TunaLibraryLoader extends Template
{
    /**
     * Template filename
     */
    protected $_template = 'Tuna_TunaGateway::tunaLibraryLoader.phtml';
}

