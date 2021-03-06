<?php
 namespace Tuna\TunaGateway\Block\Adminhtml\System\Config\Fieldset;
 
 class PaymentFieldSet extends \Magento\Config\Block\System\Config\Form\Fieldset
 {
     protected $_backendConfig;
 
     public function __construct(
         \Magento\Backend\Block\Context $context,
         \Magento\Backend\Model\Auth\Session $authSession,
         \Magento\Framework\View\Helper\Js $jsHelper,
         \Magento\Config\Model\Config $backendConfig,
         array $data = []
     ) {
         $this->_backendConfig = $backendConfig;
         parent::__construct($context, $authSession, $jsHelper, $data);
     }
 
     protected function _getFrontendClass($element)
     {
         $enabledString = ' enabled';
         return parent::_getFrontendClass($element) . ' with-button' . $enabledString;
     }
 
     protected function _isPaymentEnabled($element)
     {
         $groupConfig = $element->getGroup();
         $activityPaths = isset($groupConfig['activity_path']) ? $groupConfig['activity_path'] : [];
 
         if (!is_array($activityPaths)) {
             $activityPaths = [$activityPaths];
         }
 
         $isPaymentEnabled = false;
         foreach ($activityPaths as $activityPath) {
             $isPaymentEnabled = $isPaymentEnabled
                 || (bool)(string)$this->_backendConfig->getConfigDataValue($activityPath);
         }
 
         return $isPaymentEnabled;
     }
 
     protected function _getHeaderTitleHtml($element)
     {
         $html = '<div class="config-heading" >';
 
         $groupConfig = $element->getGroup();
 
         $htmlId = $element->getHtmlId();
         $html .= '<div class="button-container"><button type="button" class="button action-configure' .
             (empty($groupConfig['tuna']) ? '' : ' tuna-ec-separate') . 
             '" id="' .
             $htmlId .
             '-head" onclick="tunaToggleSolution.call(this, \'' .
             $htmlId .
             "', '" .
             $this->getUrl(
                 'adminhtml/*/state'
             ) . '\'); return false;"><span class="state-closed">' . __(
                 'Configure'
             ) . '</span><span class="state-opened">' . __(
                 'Close'
             ) . '</span></button>';
 
         if (!empty($groupConfig['more_url'])) {
             $html .= '<a class="link-more" href="' . $groupConfig['more_url'] . '" target="_blank">' . __(
                 'Learn More'
             ) . '</a>';
         }
         if (!empty($groupConfig['demo_url'])) {
             $html .= '<a class="link-demo" href="' . $groupConfig['demo_url'] . '" target="_blank">' . __(
                 'View Demo'
             ) . '</a>';
         }
 
         $html .= '</div>';
         $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';
 
         if ($element->getComment()) {
             $html .= '<span class="heading-intro">' . $element->getComment() . '</span>';
         }
         $html .= '<div class="config-alt"></div>';
         $html .= '</div></div>';
 
         return $html;
     }
 
     protected function _getHeaderCommentHtml($element)
     {
         return '';
     }
 
     protected function _isCollapseState($element)
     {
         return false;
     }
 
     protected function _getExtraJs($element)
     {
         $script = "require(['jquery', 'prototype'], function(jQuery){
             window.tunaToggleSolution = function (id, url) {
                 var doScroll = false;
                 Fieldset.toggleCollapse(id, url);
                 if ($(this).hasClassName(\"open\")) {
                     $$(\".with-button button.button\").each(function(anotherButton) {
                         if (anotherButton != this && $(anotherButton).hasClassName(\"open\")) {
                             $(anotherButton).click();
                             doScroll = true;
                         }
                     }.bind(this));
                 }
                 if (doScroll) {
                     var pos = Element.cumulativeOffset($(this));
                     window.scrollTo(pos[0], pos[1] - 45);
                 }
             }
         });";
 
         return $this->_jsHelper->getScript($script);
     }
 }