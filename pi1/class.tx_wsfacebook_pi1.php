<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011  Web.Sepctr <info@web-spectr.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('ws_facebook') . 'Library/facebook/src/facebook.php');


/**
 * Plugin 'Facebook Last activity' for the 'ws_facebook' extension.
 *
 * @author   Web.Spectr <info@web-spectr.com>
 * @package  TYPO3
 * @subpackage  tx_wsfacebook
 */
class tx_wsfacebook_pi1 extends tslib_pibase {
  var $prefixId      = 'tx_wsfacebook_pi1';    // Same as class name
  var $scriptRelPath = 'pi1/class.tx_wsfacebook_pi1.php';  // Path to this script relative to the extension dir.
  var $extKey        = 'ws_facebook';  // The extension key.
  var $pi_checkCHash = true;

  /**
   * The main method of the PlugIn
   *
   * @param  string    $content: The PlugIn content
   * @param  array    $conf: The PlugIn configuration
   * @return  The content that is displayed on the website
   */
  function main($content, $conf) {
    $this->conf = $conf;
    $this->pi_setPiVarDefaults();
    $this->pi_loadLL();
    $this->pi_initPIflexForm(); // Init FlexForm configuration for plugin

    $sFbUser = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'facebook');
    $sFeedLink = '/'.$sFbUser.'/feed';

    $appId = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'apiId');
    $secret = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'secret');

    // Create our Application instance (replace this with your appId and secret).
    $facebook = new Facebook(array(
      'appId' => $appId,
      'secret' => $secret,
      'cookie' => true,
    ));

    //$token =   $this->getToken($appId, $secret);

    $user = $facebook->getUser();

    $session = @$facebook->getSession();

    $wallData = array();
    $wallData = $facebook->api($sFeedLink);
    if ($session) {
      try {
        $wallData = $facebook->api($sFeedLink);
      } catch (FacebookApiException $e) {
        error_log($e);
      }
    }

    if(count($wallData)) {
      foreach($wallData['data'] as $key=>$data){
        if(!empty($data['message']) || !empty($data['description'])) {
          break;
        }
      }
      if(!empty($data['message']) || empty($data['description'])){
        $data['description'] = $data['message'];
      }
      $data['time'] = substr($data['created_time'],0,10);
      $data['time'] = t3lib_div::trimExplode('-', $data['time'], 1, 3);
      $data['time'] = mktime(0, 0, 1, $data['time'][1], $data['time'][2], $data['time'][0]);
      $data['timeFormatted'] = date("d.m.Y", $data['time']);

      $data['description'] = trim(strip_tags($data['description']));

      $reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
      // Check if there is a url in the text
      if(preg_match_all($reg_exUrl, $data['description'], $url)) {
        if($this->pi_getFFvalue($this->cObj->data['pi_flexform'])){
          $iconLink = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'iconLink');
        }
        else {
          $iconLink = t3lib_extMgm::extRelPath ('ws_facebook') . 'pi1/res/up-link.gif';
        }
        // make the urls hyper links
        foreach($url[0] as $urlItem){
          $icon = '<a href="'.$urlItem.'" target="_blank"><img src="'.$iconLink.'" alt=""/></a>';
          $data['description'] = preg_replace($reg_exUrl, $icon, $data['description']);
        }
      }

      $content = '
      <div class="fbwrap">
        <a target="_blank" href="http://www.facebook.com/'.$sFbUser.'" title="" class="facebook"></a>
      </div>
      <div class="news_item last">
        <div class="item_date">'. $data['timeFormatted'] .'</div>
        <div class="item_text">' . $data['description'] . '</div>
        <a target="_blank" href="' . htmlentities($data['link']) . '" class="more" title="">More</a>
      </div>
      ';
    }

    return $this->pi_wrapInBaseClass($content);
  }

  function getToken($appId, $secret){

    $url = 'https://graph.facebook.com/oauth/access_token';
    $url .= '?client_id='.$appId.'&client_secret='.$secret.'&grant_type=client_credentials';

    return $this->fireCurl($url);
  }

  function fireCurl($sUrl, $mRequest=array()){
    $oCurl = curl_init();
      // set URL and other appropriate options
      curl_setopt($oCurl, CURLOPT_URL, $sUrl);
      curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, 60);
      curl_setopt($oCurl, CURLOPT_TIMEOUT, 300);
      curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 0);
      curl_setopt($oCurl, CURLOPT_HEADER, 0);
      curl_setopt($oCurl, CURLOPT_POST, 0);
      curl_setopt($oCurl, CURLOPT_HTTPGET, 1);

      //https
      curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);

      //curl_setopt($oCurl, CURLOPT_POSTFIELDS, $mRequest);
      //  grab URL and pass it to the browser
      $mResponce = curl_exec($oCurl);
      $status = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);

      if ($mResponce === false) {
          $sError = curl_error($oCurl);
      }

      // close cURL resource, and free up system resources
      curl_close($oCurl);
      return $mResponce;
  }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ws_facebook/pi1/class.tx_wsfacebook_pi1.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ws_facebook/pi1/class.tx_wsfacebook_pi1.php']);
}

?>
