<?php

namespace Klump\Payment\Block\System\Config\Fieldset;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Fieldset renderer for Klump payment
 */
class Payment extends \Magento\Config\Block\System\Config\Form\Fieldset
{


  /**
   * Add custom css class
   *
   * @param AbstractElement $element
   * @return string
   */
  protected function _getFrontendClass($element)
  {
    return parent::_getFrontendClass($element) . ' with-button';
  }

   /**
   * Return header title part of html for Klump payments
   *
   * @param AbstractElement $element
   * @return string
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function _getHeaderTitleHtml($element)
  {
    $html = '<div class="config-heading" >';

    // $groupConfig = $element->getGroup();

    // $disabledAttributeString = $this->_isPaymentEnabled($element) ? '' : ' disabled="disabled"';
    // $disabledClassString = $this->_isPaymentEnabled($element) ? '' : ' disabled';
    $html_id = $element->getHtmlId();
    $inline_style = 'float: right;';
    $heading_style = "";
    $html .= '<div class="button-container admin__collapsible-block klump-configure">'.
          '<button class="button" id="' .
          $html_id .
          '-head" href="#' .
          $html_id .
          '-link" onclick="Fieldset.toggleCollapse(\'' .
          $html_id .
          '\', \'' .
          $this->getUrl(
              '*/*/state'
          ) . '\'); return false;"></button>';


    $html .= '</div>';
    $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';

    if ($element->getComment()) {
      $html .= '<span class="heading-intro">' . $element->getComment() . '</span>';
    }
    $html .= '<div class="config-alt"></div>';
    $html .= '</div></div>';

    return $html;
  }


  /**
   * Return header comment part of html for Klump payments
   *
   * @param AbstractElement $element
   * @return string
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  protected function _getHeaderCommentHtml($element)
  {
    return '';
  }
}
