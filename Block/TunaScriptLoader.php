<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\View\Element\Template;

/**
 * Block to load and expose Tuna library globally
 * Intercepts the AMD define() call to capture the Tuna module and expose it as window.Tuna
 */
class TunaScriptLoader extends Template
{
    protected $_template = 'Tuna_TunaGateway::tunaScriptLoader.phtml';
}
