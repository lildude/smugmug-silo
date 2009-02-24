<?php
/**
 *
 * Copyright 2009 Colin Seymour - http://phpsmug.com/smugmug-silo-plugin
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * SmugMug Silo
 *
 * @todo: add debug stuff
 * @todo: Get SmugMugSilo specific API key
 */

class SmugMugSilo extends Plugin implements MediaSilo
{
    const SILO_NAME = 'SmugMug';
	const DEBUG = FALSE;		// Only change this if you absolutely have to.

    const APIKEY = 'woTP74YfM4zRoScpGFdHYPMLRYZSEhl2';
    const OAUTHSECRET = '5a3707ce2c2afadaa5a5e0c1c327ccae';
    const CACHE_EXPIRY = 86400;	// seconds.  This is 24 hours.

    /**
    * Provide plugin info to the system
    */
    public function info()
    {
		return array(
			'name'			=> 'SmugMug Media Silo',
			'version'		=> '0.1',
			'url'			=> 'http://phpsmug.com/smugmug-silo-plugin',
			'author'		=> 'Colin Seymour',
			'authorurl'		=> 'http://www.colinseymour.co.uk/',
			'license'		=> 'Apache License 2.0',
			'description'	=> 'Provides a silo to access your SmugMug photos',
			'copyright'		=> date('Y'),
			'guid'			=> '4A881D3E-E643-11DD-8D7A-AA9D55D89593'
		);
    }

    /**
    * Initialize some internal values when plugin initializes
    */
    public function action_init()
    {
		if ( !class_exists( 'phpSmug' ) ) {
			require_once( dirname( __FILE__ ).'/lib/phpSmug/phpSmug.php' );
		}
		$this->smug = new phpSmug( "APIKey=".self::APIKEY,
								   "AppName={$this->info->name}/{$this->info->version}",
								   "OAuthSecret=".self::OAUTHSECRET );
		// Enable caching.  This will be for 24 hours, but will be cleared whenever
		// a file is uploaded via this plugin or manually via the silo.
		$this->smug->enableCache( "type=fs",
								  "cache_dir=". HABARI_PATH . '/user/cache/',
								  "cache_expire=".self::CACHE_EXPIRY );
    }

    /**
    * Return basic information about this silo
    *   name- The name of the silo, used as the root directory for media in this silo
    *   icon- An icon to represent the silo
    */
    public function silo_info()
    {
		if( $this->is_auth() ) {
			return array( 'name' => self::SILO_NAME,
						  'icon' => URL::get_from_filesystem(__FILE__) . '/lib/imgs/icon.png' );
		}
		else {
			return array();
		}
    }

    /**
    * Return directory contents for the silo path
    *
    * @param string $path The path to retrieve the contents of
    * @return array An array of MediaAssets describing the contents of the directory
    */
    public function silo_dir($path)
    {
		$token = User::identify()->info->smugmugsilo__token;
		$user  = User::identify()->info->smugmugsilo__nickName;
		$this->smug->setToken( "id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}" );
		$img_extras = 'Hidden,Caption,Format,AlbumURL,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL,FileName'; // Grab only the options we need to keep the response small
		$results = array();
		$section = strtok( $path, '/' );

		switch( $section ) {
			case 'recentPhotos':
			$cache_name = ( array( 'smugmugsilo', "recentphotos".$user ) );
			if ( Cache::has( $cache_name ) ) {
				$results = Cache::get( $cache_name );
			}
			else {
				$photos = self::getFeed( 10 );
				$i = 0; $ids = array();
				foreach( $photos->entry as $photo ) {
					$attribs = $photo->link->attributes();
					$ids[$i] = (string) $attribs->href;
					$i++;
				}
				foreach( $ids as $photoURL ) {
					$idKey = explode( '#', $photoURL );
					list( $id, $key ) = explode( '_', $idKey[1] );
					$info = $this->smug->images_getInfo( "ImageID={$id}", 
														 "ImageKey={$key}",
														 "Extras={$img_extras}" );
					$props = array( 'Title' => '', 'FileName' => '', 'Hidden' => 0 );
					foreach( $info as $name => $value ) {
						$props[$name] = (string) $value;
						$props['filetype'] = 'smugmug';
						if ( $name == "Caption" ) {
							$props['Title'] = self::setTitle( $props, $value );
						}

						$results[] = new MediaAsset(
										self::SILO_NAME . '/recentPhotos/' . $id,
										false,
										$props
										);
					}
				}
				Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
			}

			break;
			case 'recentGalleries':
				$selected_gallery = strtok( '/' );
				$galmeta = explode( '_', $selected_gallery );
				if ( $selected_gallery ) {
					$cache_name = ( array( 'smugmugsilo', $selected_gallery.$user ) );
					if ( Cache::has( $cache_name ) ) {
						$results = Cache::get( $cache_name );
					}
					else {
						$props = array( 'Title' => '', 'FileName' => '', 'Hidden' => 0 );
						$photos = $this->smug->images_get( "AlbumID={$galmeta[0]}", 
														   "AlbumKey={$galmeta[1]}",
														   "Extras={$img_extras}" );
						foreach( $photos['Images'] as $photo ) {
							foreach( $photo as $name => $value ) {
								$props[$name] = (string)$value;
								$props['filetype'] = 'smugmug';
								if ( $name == "Caption" ) {
									$props['Title'] = self::setTitle( $props, $value );
								}
							}

							$results[] = new MediaAsset(
											self::SILO_NAME . '/photos/' . $photo['id'],
											false,
											$props
											);
						}
						Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
					}
				} else {
					$cache_name = ( array( 'smugmugsilo', "recentgalleries".$user ) );
					if ( Cache::has( $cache_name ) ) {
						$results = Cache::get( $cache_name );
					}
					else {
						$photos = self::getFeed( 10, 'Galleries' );
						$i = 0; $ids = array();
						foreach( $photos->entry as $photo ) {
							$attribs = $photo->link->attributes();
							$ids[$i] = (string) $attribs->href;
							$i++;
						}
						$j = 0;
						$galleries = array();
						foreach( $ids as $galURL ) {
							$idKey = explode( '/', $galURL );
							list( $id, $key ) = explode( '_', end($idKey ) );
							$galleries[$j] = $this->smug->albums_getInfo( "AlbumID={$id}",
																		  "AlbumKey={$key}" );
							$j++;
						}
						foreach( $galleries as $gallery ) {
							$results[] = new MediaAsset(
											self::SILO_NAME . '/recentGalleries/' . (string) $gallery['id'].'_'.$gallery['Key'],
											true,
											array('title' => (string) $gallery['Title'])
											);
						}
					}
					Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
				}
			break;
			case 'galleries':
				$selected_gallery = strtok( '/' );
				$galmeta = explode( '_', $selected_gallery );
				if ( $selected_gallery ) {
					$cache_name = ( array( 'smugmugsilo', $selected_gallery ) );
					if ( Cache::has( $cache_name ) ) {
						$results = Cache::get( $cache_name );
					}
					else {
						$props = array( 'Title' => '', 'FileName' => '', 'Hidden' => 0 );
						$photos = $this->smug->images_get( "AlbumID={$galmeta[0]}", 
														   "AlbumKey={$galmeta[1]}",
														   "Extras={$img_extras}" );
						foreach( $photos['Images'] as $photo ) {
							foreach( $photo as $name => $value ) {
								$props[$name] = (string) $value;
								$props['filetype'] = 'smugmug';
								if ( $name == "Caption" ) {
									$props['Title'] = self::setTitle( $props, $value );
								}
							}

							$results[] = new MediaAsset(
											self::SILO_NAME . '/photos/' . $photo['id'],
											false,
											$props
											);
						}
					Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
					}
				} else {
					// Don't need to cache this as it's quick anyway.
					$galleries = $this->smug->albums_get( "Extras=Public" );
					foreach( $galleries as $gallery ) {
						$results[] = new MediaAsset(
										self::SILO_NAME . '/galleries/' . (string) $gallery['id'].'_'.$gallery['Key'],
										true,
										// If the gallery is NOT public, mark it by preceding with a lock icon
										// This is a bit of the fudge as the MediaAsset takes an icon argument, but doesn't actually do anything with it.  This would be a great place for it to use it in this case. It would be great if it did.
										// @todo: Look into adding the necessary code to Habari so the icon passed to MediaAsset IS used
										array( 'title' => ( ( $gallery['Public'] == TRUE ) ? '' : '<img src="'.URL::get_from_filesystem( __FILE__ ) . '/lib/imgs/lock.png" style="vertical-align: middle; height:12px; width:12px" title="Private Gallery" /> ' ).$gallery['Title'] )
										);
					}

				}
			break;
			case '':
				$results[] = new MediaAsset(
								self::SILO_NAME . '/galleries',
								true,
								array( 'title' => 'All Galleries' )
								);
				$results[] = new MediaAsset(
								self::SILO_NAME . '/recentGalleries',
								true,
								array( 'title' => 'Recent Galleries' )
								);
				$results[] = new MediaAsset(
								self::SILO_NAME . '/recentPhotos',
								true,
								array( 'title' => 'Recent Photos' )
								);
			break;
		}
		return $results;
    }

   /**
   * Get the file from the specified path
   *
   * @param string $path The path of the file to retrieve
   * @param array $qualities Qualities that specify the version of the file to retrieve.
   * @return MediaAsset The requested asset
   */
   public function silo_get($path, $qualities = null)
   {
   }

   /**
   * Create a new asset instance for the specified path
   *
   * @param string $path The path of the new file to create
   * @return MediaAsset The requested asset
   */
   public function silo_new($path)
   {
   }

   /**
   * Store the specified media at the specified path
   *
   * @param string $path The path of the file to retrieve
   * @param MediaAsset $ The asset to store
   */
   public function silo_put($path, $filedata)
   {
   }

   /**
   * Delete the file at the specified path
   *
   * @param string $path The path of the file to retrieve
   */
   public function silo_delete($path)
   {
   }

   /**
   * Retrieve a set of highlights from this silo
   * This would include things like recently uploaded assets, or top downloads
   *
   * @return array An array of MediaAssets to highlihgt from this silo
   */
   public function silo_highlights()
   {
   }

   /**
   * Retrieve the permissions for the current user to access the specified path
   *
   * @param string $path The path to retrieve permissions for
   * @return array An array of permissions constants (MediaSilo::PERM_READ, MediaSilo::PERM_WRITE)
   */
   public function silo_permissions($path)
   {
   }

   /**
   * Return directory contents for the silo path
   *
   * @param string $path The path to retrieve the contents of
   * @return array An array of MediaAssets describing the contents of the directory
   */
   public function silo_contents()
   {
   }


	/**
	 * Return a link for the panel at the top of the silo window.
	 */
	public function link_panel( $path, $panel, $title )
    {
	    return '<a href="#" onclick="habari.media.showpanel(\''.$path.'\', \''.$panel.'\');return false;">' . $title . '</a>';
    }

    /**
     * Provide controls for the media control bar
     *
     * @param array $controls Incoming controls from other plugins
     * @param MediaSilo $silo An instance of a MediaSilo
     * @param string $path The path to get controls for
     * @param string $panelname The name of the requested panel, if none then emptystring
     * @return array The altered $controls array with new (or removed) controls
     */
    public function filter_media_controls( $controls, $silo, $path, $panelname )
    {
	    $class = __CLASS__;
	    if( $silo instanceof $class ) {
		    $controls['root'] = '';
		    $controls[] = $this->link_panel( self::SILO_NAME . '/' . $path, 'clearCache', _t( 'ClearCache' ) );
		    if( User::identify()->can( 'upload_smugmug' ) ) {
			    if ( strchr( $path, '/' ) ) {
				    $controls[] = $this->link_panel( self::SILO_NAME . '/' . $path, 'upload', _t( 'Upload' ) );
			    }
		    }
	    }
	    return $controls;
    }

    /**
     * Provide requested media panels for this plugin
     *
     * @param string $panel The HTML content of the panel to be output in the media bar
     * @param MediaSilo $silo The silo for which the panel was requested
     * @param string $path The path within the silo (silo root omitted) for which the panel was requested
     * @param string $panelname The name of the requested panel
     * @return string The modified $panel to contain the HTML output for the requested panel
     */
    public function filter_media_panels( $panel, $silo, $path, $panelname )
    {
	    $class = __CLASS__;
	    if( $silo instanceof $class ) {
		    switch( $panelname ) {
			    case 'clearCache':
					$this->clearCaches();
					$panel .= "<div style='width: 200px; margin:10px auto; text-align:center;'><p>Cache Cleared</p>";
					$panel .= '<p><br/><strong><a href="#" onclick="habari.media.forceReload();habari.media.clickdir(\''. self::SILO_NAME . '/'. $path . '\');habari.media.showdir(\''. self::SILO_NAME . '/'. $path . '\');">'._t('Return to current silo path.').'</a></strong></p></div>';
				break;
			    case 'upload':
				    if( isset( $_FILES['file'] ) ) {
					    try {
						    $result = $this->smug->images_upload( "AlbumID={$_POST['AlbumID']}",
																  "File={$_FILES['file']['tmp_name']}",
																  "FileName={$_FILES['file']['name']}",
																  "Caption={$_POST['Caption']}",
																  "Keywords={$_POST['Keywords']}" );
						    $img = $this->smug->images_getURLs( "ImageID={$result['id']}",
																"ImageKey={$result['Key']}" );
						    $output = "<p><img src={$img['TinyURL']} /></p>";
						    $output .= "<p>Image successfully uploaded</p>";
					    }
					    catch ( Exception $e ) {
						    $output = $e->getMessage();
					    }

						$this->clearCaches();
					    $panel .= "<div style='width: 200px; margin:10px auto; text-align:center;'>{$output}";
					    $panel .= '<p><br/><strong><a href="#" onclick="habari.media.forceReload();habari.media.clickdir(\''. self::SILO_NAME . '/'. $path . '\');habari.media.showdir(\''. self::SILO_NAME . '/'. $path . '\');">Browse the current silo path.</a></strong></p></div>';
				    }
				    else {
					    $fullpath = self::SILO_NAME . '/' . $path;
					    $form_action = URL::get( 'admin_ajax', array( 'context' => 'media_panel' ) );
					    $gal = explode( '/', $path );
					    $gal = explode( '_', $gal[1] );
					    $gallery = $this->smug->albums_getInfo( "AlbumID={$gal[0]}",
																"AlbumKey={$gal[1]}" );
					    $panel.= <<<UPLOAD_FORM
<form enctype="multipart/form-data" method="post" id="simple_upload" target="simple_upload_frame" action="{$form_action}" class="span-10" style="margin:0px auto;text-align: center">
<p><input type="hidden" name="path" value="{$fullpath}">
    <input type="hidden" name="AlbumID" value="{$gal[0]}">
    <input type="hidden" name="panel" value="{$panelname}"></p>
    <p><table style="margin: 0 auto; padding-top:10px; padding:5px; border-spacing: 2px;">
<tr><td style="text-align:right;">Upload image to:</td><td  style="text-align:right;"><b style="color: #e0e0e0;font-size: 1.2em;">{$gallery['Title']}</b></td></tr>
<tr><td colspan="2"><input type="file" name="file"></td></tr>
<tr><td style="padding-top: 5px; text-align:right;">Optional Settings:</td><td>&nbsp;</td></tr>
<tr><td style="text-align:right;"><label for="caption">Caption:</label></td><td style="text-align:right;"><input type="text" name="Caption" /></td></tr>
<tr><td style="text-align:right;"><label for="keywords">Keywords:</label></td><td style="text-align:right;"><input type="text" name="Keywords" /></td></tr>
<tr><td style="text-align:right;padding-top: 5px;"><button onclick="habari.media.forceReload();habari.media.clickdir('SmugMug/{$path}');habari.media.showdir('SmugMug/{$path}'); return false;">Cancel</button></td><td style="text-align:right;padding-top: 5px;"><input type="submit" name="upload" value="Upload" onclick="spinner.start();" /></td></tr>
</table></p>
</form>
<iframe id="simple_upload_frame" name="simple_upload_frame" style="width:1px;height:1px;" onload="simple_uploaded();"></iframe>
<script type="text/javascript">
var responsedata;
function simple_uploaded() {
    if(!$('#simple_upload_frame')[0].contentWindow) return;
    var response = $($('#simple_upload_frame')[0].contentWindow.document.body).text();
    if(response) {
	    eval('responsedata = ' + response);
	    window.setTimeout(simple_uploaded_complete, 500);
    }
}
function simple_uploaded_complete() {
    spinner.stop();
    habari.media.jsonpanel(responsedata);
}
</script>
UPLOAD_FORM;

					}
			    break;
		    }
		    return $panel;
	    }
    }


    /**
    * Add actions to the plugin page for this plugin
    * The authorization should probably be done per-user.
    *
    * @param array $actions An array of actions that apply to this plugin
    * @param string $plugin_id The string id of a plugin, generated by the system
    * @return array The array of actions to attach to the specified $plugin_id
    */
    public function filter_plugin_config( $actions, $plugin_id )
    {
	    if ( $plugin_id == $this->plugin_id() ){
		    $phpSmug_ok = $this->is_auth();

		    if( $phpSmug_ok ){
			    $actions[] = _t( 'Configure' );
			    $actions[] = _t( 'De-Authorize' );
		    } else {
			    $actions[] = _t( 'Authorize' );
		    }
	    }
	    return $actions;
    }

    /**
    * Respond to the user selecting an action on the plugin page
    *
    * @param string $plugin_id The string id of the acted-upon plugin
    * @param string $action The action string supplied via the filter_plugin_config hook
    */
    public function action_plugin_ui( $plugin_id, $action )
    {
	    if ( $plugin_id == $this->plugin_id() ){
		    switch ( $action ){
			    case _t( 'Authorize' ):
				    if( $this->is_auth() ){
					    $deauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'De-Authorize' ) ) . '#plugin_options';
					    echo '<p>'._t( 'You have already successfully authorized Habari to access your SmugMug account' ).'.</p>';
					    echo '<p>'._t( 'Do you want to ' )."<a href=\"{$deauth_url}\">"._t( 'revoke authorization' ).'</a>?</p>';
				    } else {
					    try {
							$reqToken = $this->smug->auth_getRequestToken();
							$_SESSION['SmugGalReqToken'] = serialize( $reqToken );
							$confirm_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm' ) ) . '#plugin_options';
						    echo '<form><table style="border-spacing: 5px; width: 100%;"><tr><td>'._t( 'To use this plugin, you must authorize Habari to have access to your SmugMug account' ).".</td>";
							echo "<td><button id='auth' style='margin-left:10px;' onclick=\"window.open('{$this->smug->authorize( "Access=Full", "Permissions=Modify" )}', '_blank').focus();return false;\">"._t( 'Authorize' )."</button></td></tr>";
							echo '<tr><td>'._t( 'When you have completed the authorization on SmugMug, return here and confirm that the authorization was successful.' )."</td>";
							echo "<td><button disabled='true' id='conf' style='margin-left:10px;' onclick=\"location.href='{$confirm_url}'; return false;\">"._t( 'Confirm' )."</button></td></tr>";
							echo '</table></form>';
						}
						catch ( Exception $e ) {
							Session::error( $e->getMessage(), 'SmugMug API' );
							EventLog::log( $e->getMessage() );
							echo "<p>Ooops. SmugMug didn't like that call: {$e->getMessage()}</p>";
						}
				    }
				break;

			    case 'confirm':
				    if( !isset( $_SESSION['SmugGalReqToken'] ) ){
					    $auth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize' ) ) . '#plugin_options';
					    echo '<form><p>'._t( 'Either you have already authorized Habari to access your SmugMug account, or you have not yet done so.  Please' ).'<strong><a href="' . $auth_url . '">'._t( 'try again' ).'</a></strong>.</p></form>';
				    } else {
					    $reqToken = unserialize( $_SESSION['SmugGalReqToken'] );
					    $this->smug->setToken( "id={$reqToken['id']}", "Secret={$reqToken['Secret']}" );
					    $token = $this->smug->auth_getAccessToken();

					    // Lets speed things up a bit by pre-fetching all the gallery info and caching it
					    $this->smug->setToken( "id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}" );
					    $this->smug->albums_get();

					    $config_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Configure' ) ) . '#plugin_options';

					    if( isset( $token ) ){
							$user = User::identify();
							$user->info->smugmugsilo__token = $token;
							$user->info->smugmugsilo__nickName = $token['User']['NickName'];
							// Set required default config options at the same time - the others are really optional.
							$user->info->smugmugsilo__image_size = 'S';
							$user->info->smugmugsilo__use_thickbox = FALSE ;
							$user->info->commit();
						    EventLog::log( _t( 'Authorization Confirmed.' ) );
						    echo '<form><p>'._t( 'Your authorization was set successfully. You can now <b><a href="'.$config_url.'">configure</a></b> the SmugMug Silo to suit your needs.' ).'</p></form>';
					    }
					    else{
						    echo '<form><p>'._t( 'There was a problem with your authorization:' ).'</p></form>';
					    }
					    unset( $_SESSION['SmugGalReqToken'] );
				    }
				break;
			    case _t( 'De-Authorize' ):
					User::identify()->info->smugmugsilo__token = '';
					User::identify()->info->commit();
					// Clear the cache
					$this->clearCaches();
				    $reauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize' ) ) . '#plugin_options';
				    echo '<form><p>'._t( 'The SmugMug Silo Plugin authorization has been deleted. Please ensure you revoke access from your SmugMug Control Panel too.' ).'<p>';
				    echo "<p>"._t( 'Do you want to ' )."<b><a href=\"{$reauth_url}\">"._t( 're-authorize this plugin' )."</a></b>?<p></form>";
				    EventLog::log( _t( 'De-authorized' ) );

				break;
			    case _t( 'Configure' ) :
					$user = User::identify();
					$token = $user->info->smugmugsilo__token;
					$customSize = $user->info->smugmugsilo__custom_size;
					$imageSize = $user->info->smugmugsilo__image_size;
					$useTB = $user->info->smugmugsilo__use_thickbox;

				    $ui = new FormUI( strtolower( get_class( $this ) ) );
				    $ui->append( 'select', 'image_size','user:smugmugsilo__image_size', _t( 'Default size for images in Posts:' ) );
				    $ui->append( 'text', 'custom_size', 'user:smugmugsilo__custom_size', _t( 'Custom Size of Longest Edge (px):' ) );
					if ( $imageSize != 'Custom' ) {
					    $ui->custom_size->class = 'formcontrol hidden';
				    }
				    $ui->image_size->options = array( 'Ti' => 'Tiny', 'Th' => 'Thumbnail', 'S' => 'Small', 'M' => 'Medium', 'L' => 'Large (if available)', 'XL' => 'XLarge (if available)', 'X2' => 'X2Large (if available)', 'X3' => 'X3Large (if available)', 'O' => 'Original (if available)', 'Custom' => 'Custom (Longest edge in px)' );
				    // If Thickbox enabled, give option of using it, and what img size to show:
				    // Commenting out as people may have their own installation of thickbox and not the plugin
					// if ( Plugins::is_loaded( 'Thickbox' ) ) { // Requires svn r2903 or later due to ticket #754
					    $ui->append( 'fieldset', 'tbfs', 'ThickBox' );
					    //$ui->tbfs->append( 'label', 'tbfs', _t( "If You have the Thickbox available in your theme or via the Thickbox plugin, you can take advantage of it's functionality with the SmugMug Silo if you wish." ) );
					    $ui->tbfs->append( 'checkbox', 'use_tb', 'user:smugmugsilo__use_thickbox', _t( 'Use Thickbox?' ) );
					    $ui->tbfs->append( 'select', 'tb_image_size', 'user:smugmugsilo__thickbox_img_size', _t( 'Image size to use for Thickbox (warning: large images are slow to load):' ) );
						if ( $useTB == FALSE ) {
						    $ui->tb_image_size->class = 'formcontrol hidden';
					    }
					    $ui->tbfs->tb_image_size->options = array( 'MediumURL' => 'Medium', 'LargeURL' => 'Large (if available)', 'XLargeURL' => 'XLarge (if available)', 'X2LargeURL' => 'X2Large (if available)', 'X3LargeURL' => 'X3Large (if available)', 'OriginalURL' => 'Original (if available)' );
				    // } // End of if is_loaded()
				    $ui->append( 'submit', 'save', _t( 'Save Options' ) );
				    $ui->set_option( 'success_message', _t( 'Options successfully saved.' ) );
				    $ui->out();
				break;
		    }
	    }
    }


    /**
     * Clear cache files when de-activating
     **/
    public function action_plugin_deactivation( $file )
    {
		if ( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
			// todo: Comment out the options deletion prior to go-live.
			$user = User::identify();
			unset( $user->info->smugmugsilo__token );
			unset( $user->info->smugmugsilo__thickbox_img_size );
			unset( $user->info->smugmugsilo__custom_size );
			unset( $user->info->smugmugsilo__image_size );
			unset( $user->info->smugmugsilo__use_thickbox );
			unset( $user->info->smugmugsilo__nickName );
			$user->info->commit();
			$this->clearCaches();
			rmdir( $this->smug->cache_dir );
		}
    }

    /**
     * Add custom styling and Javascript controls to the footer of the admin interface
     **/
    public function action_admin_footer( $theme ) {
	    // Load LazyLoad jQuery plugin
	    if( Controller::get_var( 'page' ) == 'publish' ) {
			$user = User::identify();
			$size = $user->info->smugmugsilo__image_size;
		    if ( $size == "Custom" ) {
				$customSize = $user->info->smugmugsilo__custom_size;
			    $size = "{$customSize}x{$customSize}";
		    }
		    $nickName = $user->info->smugmugsilo__nickName;
			$useThickBox = $user->info->smugmugsilo__use_thickbox;
			$thickBoxSize = $user->info->smugmugsilo__thickbox_img_size;
			$lockicon = URL::get_from_filesystem( __FILE__ ) . '/lib/imgs/bwlock2.png';

		    echo <<< SMUGMUG_ENTRY_CSS_1
		    <style type="text/css">
		    div.smugmug ul.mediaactions.dropbutton li { display: inline !important; float:left; width:9%; min-width: 5px; }
		    div.smugmug ul.mediaactions.dropbutton li.first-child a { background: none !important; }
		    div.smugmug ul.mediaactions.dropbutton li.first-child:hover { -moz-border-radius-bottomleft: 3px !important;  -webkit-border-bottom-left-radius: 3px !important; -moz-border-radius-topright: 0px !important;  -webkit-border-top-right-radius: 0px !important; }
		    div.smugmug ul.mediaactions.dropbutton li.last-child { -moz-border-radius-bottomleft: 0px !important;  -webkit-border-bottom-left-radius: 0px !important; border-right: none; }
		    div.smugmug ul.mediaactions.dropbutton li.last-child:hover { -moz-border-radius-topright: 3px !important;  -webkit-border-top-right-radius: 3px !important; -moz-border-radius-bottomright: 3px !important;  -webkit-border-bottom-right-radius: 3px !important; padding-right: 7px !important; }
		    span.hidden_img { background: transparent url('{$lockicon}') no-repeat 0 50%; width: 16px; height: 32px; float: left;}
		    </style>
		    <script type="text/javascript">
			    /* Get the silo id from the href of the link and add class to that siloid */
			    var siloid = $("a:contains('SmugMug')").attr("href");
			    $(siloid).addClass('smugmug');

			    /* Disable the search box and make it light grey as it's useless to us */
			    $('.smugmug div.media_controls > input').attr("disabled", true).css("background-color", "#ccc");

			    /* This is a bit of a fudge to over-write the dblclick functionality.
			       We introduce our own dblclick which inserts the default image size as defined by the user.

			       I use mouseover here because media.js, which sets the initial dblclick, is reloaded each time
			       the user clicks on a "dir" entry, but this code isn't. */
			    $('.smugmug .mediaphotos').bind('mouseover', function() {
					    $('.smugmug .media').unbind('dblclick');
					    $('.smugmug .media').dblclick(function(){
							    var id = $('.foroutput', this).html();
							    insert_smugmug_photo(id, habari.media.assets[id], "Default");
							    return false;
					    });
			    });

			    habari.media.output.smugmug = {
				    Ti: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.TinyURL);},
				    Th: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.ThumbURL);},
				    S: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.SmallURL);},
				    M: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.MediumURL);},
				    L: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.LargeURL);}
			    }


			    function insert_smugmug_photo(fileindex, fileobj, filesizeURL) {
				    if (filesizeURL == "Default") {
					    filesizeURL = "http://{$nickName}.smugmug.com/photos/"+fileobj.id+"_"+fileobj.Key+"-{$size}."+fileobj.Format;
				    }

SMUGMUG_ENTRY_CSS_1;

if ( $useThickBox && Plugins::is_loaded( 'Thickbox' ) ) {
    echo "habari.editor.insertSelection('<a class=\"thickbox\" href=\"' + fileobj.{$thickBoxSize} + '\" title=\"'+ fileobj.Caption + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" /></a>');";
} else {
    echo "habari.editor.insertSelection('<a href=\"' + fileobj.AlbumURL + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" title=\"'+ fileobj.Caption + '\" /></a>');";
}

echo <<< SMUGMUG_ENTRY_CSS_2

			    }

			    /* todo: need to lazy load this as it's quite slow */
			    habari.media.preview.smugmug = function(fileindex, fileobj) {
				    out = '<div class="mediatitle">';
				    if (fileobj.Hidden == 1) {
					    out += '<span class="hidden_img"></span>';	/* This is a bit of a nasty fudge, but it gets the job done. */
				    }
				    out += '<a href="' + fileobj.AlbumURL + '" class="medialink" target="_blank">media</a>' + fileobj.Title + '</div><img src="' + fileobj.ThumbURL + '">';
				    return out;
			    }
		    </script>
SMUGMUG_ENTRY_CSS_2;
	    }
	    // Javascript required for config panel
	    if ( Controller::get_var( 'configure' ) == $this->plugin_id ) {
		    // Authorize specific Javascript
		    if ( Controller::get_var( 'configaction' ) == 'Authorize' ) {
			    echo <<< SMUGMUG_AUTH_JS
				    <script type="text/javascript">
				    $("#auth").toggle(
					    function () {
						    $('#conf').removeAttr("disabled");
					    },
					    function () {
						    $('#conf').attr("disabled", true);
					    });
				    </script>
SMUGMUG_AUTH_JS;
		    }
		    // Configure specific Javascript
		    if ( Controller::get_var( 'configaction' ) == 'Configure' ) {
			    echo <<< SMUGMUG_CONFIG_JS
				    <script type="text/javascript">
				    if ($("#image_size select :selected").val() == 'Custom') {
					    $("#custom_size").removeClass("hidden");
						    } else {
					    $("#custom_size").addClass("hidden");

					    }
				    $("#image_size select").change(function () {
					    if (this.value == "Custom") {
						    $("#custom_size").removeClass("hidden");
					    } else {
						    $("#custom_size").addClass("hidden");
					    }
				    });

				    if ($("#use_tb input[type=checkbox]").is(":checked")) {
					    $("#tb_image_size").removeClass("hidden");
						    } else {
					    $("#tb_image_size").addClass("hidden");

					    }
				    $("#use_tb input[type=checkbox]").click(function () {
					    if ($("#use_tb input[type=checkbox]").is(":checked")) {
						    $("#tb_image_size").removeClass("hidden");
					    } else {
						    $("#tb_image_size").addClass("hidden");
					    }
				    });
				    </script>
SMUGMUG_CONFIG_JS;
		    }
	    }
    }

	/************************* HELPER METHODS *********************************/

	/**
	 * Function for easily setting the image title
	 *
	 * SmugMug has no concept of Titles, so we strip all HTML tags, split the
	 * caption by new lines and truncate the first line to 23 chars and use this
	 * for the title. If the title is empty (ie, there's no caption), we rely
	 * on the filename.
	 */
	private static function setTitle( $props, $value ) {
		$val = nl2br( strip_tags( $value ) );
		$val = explode( '<br />', $val );
		$props['Title'] = ( $props['Hidden'] == 1 ) ? self::truncate( $val[0], 18 ) : self::truncate( $val[0] );
		if ( $props['Title'] == '' ) {
			$props['Title'] = $props['FileName'];
		}
		return $props['Title'];
	}

	/**
	 * Simple function to call once to clear multiple cache locations
	 *
	 */
	private function clearCaches() {
		$this->smug->clearCache();
		foreach ( Cache::get_group( 'smugmugsilo' ) as $name => $data ) {
			Cache::expire( array( 'smugmugsilo', $name ) );
		}
	}

    /**
     * Check if the application has been authorised to access SmugMug
     **/
    private function is_auth()
    {
	    static $phpSmug_ok = null;
	    if( isset( $phpSmug_ok ) ){
		    return $phpSmug_ok;
	    }

	    $phpSmug_ok = false;
		$token = User::identify()->info->smugmugsilo__token;

		if( $token != '' ){
		    $this->smug->setToken( "id={$token['Token']['id']}",
								   "Secret={$token['Token']['Secret']}" );
			try {
				$result = $this->smug->auth_checkAccessToken();
				if( isset( $result ) ){
					$phpSmug_ok = true;
				}
			}
			catch ( Exception $e ) {
				Session::error( $e->getMessage().' Please re-authorize your plugin.', 'SmugMug API' );
				User::identify()->info->smugmugsilo__token = '';
				User::identify()->info->commit();
			    unset( $_SESSION['smugmug_token'] );
			}
	    }
	    return $phpSmug_ok;
    }

    /**
     * Function to truncate strings to a set number of characters (23 by default)
     * This is used to truncate the "Title" when displaying the images in the silo
     **/
    private static function truncate( $string, $max = 22, $replacement = '...' )
    {
	    if ( strlen( $string ) <= $max ) {
		    return $string;
	    }
	    $leave = $max - strlen( $replacement );
	    return substr_replace( $string, $replacement, $leave );
    }

	/**
	 * Function that grabs the specified Feed from SmugMug
	 * 
	 * We use the Atom Feeds offered by SmugMug for this info as there are no
	 * API entry points for recent imgs/galleries and searching
	 */
    private function getFeed( $num = 10, $type = "Photos", $keyword = NULL )
	{

		$nickName = User::identify()->info->smugmugsilo__nickName;
	    switch( $type ) {
		    case 'Photos':
			    $urlEnd = "Type=nicknameRecent&Data={$nickName}&format=atom10&ImageCount={$num}";
			break;
		    case 'Galleries':
			    $urlEnd = "Type=nickname&Data={$nickName}&format=atom10&ImageCount={$num}";
			break;
		    case 'Search':
			    $urlEnd = "Type=userkeyword&NickName={$nickName}&Data={$keyword}&format=atom10&ImageCount={$num}";
			break;
	    }
	    $url = "http://api.smugmug.com/hack/feed.mg?{$urlEnd}";
	    $this->cache_name = array ('smugmugsilo', "{$urlEnd}".$nickName );
	    if ( Cache::has( $this->cache_name ) ) {
		    $response = Cache::get( $this->cache_name );
	    } else {
		    $call = new RemoteRequest( $url );
		    $call->set_timeout( 5 );
		    $result = $call->execute();
		    if ( Error::is_error( $result ) ){
			    throw $result;
		    }
		    $response = $call->get_response_body();
		    Cache::set( $this->cache_name, $response );
	    }

	    try{
		    $xml = new SimpleXMLElement( $response );
		    return $xml;
	    }
	    catch( Exception $e ) {
		    Session::error( 'Currently unable to connect to SmugMug.', 'SmugMug API' );
		    return false;
	    }
    }
}

?>
