<?php
/**
* SmugMug Silo
*
* TODO: 
*	- Implement upload functionality: Done, just needs tidying
*	- Sort out cache clear up, but need to fix phpSmug first
*	- Add "clear cache" button to silo actions bar
*	- Think about offering galleries/categories/latest etc when initially opening silo
*
*/

require_once(dirname(__FILE__).'/phpSmug/phpSmug.php');

class SmugMugSilo extends Plugin implements MediaSilo
{
	const SILO_NAME = 'SmugMug';
	var $APIKey = 'woTP74YfM4zRoScpGFdHYPMLRYZSEhl2';
	var $OAuthSecret = '5a3707ce2c2afadaa5a5e0c1c327ccae';
	var $cache_name;

	protected $cache = array();

	/**
	* Provide plugin info to the system
	*/
	public function info()
	{
		return array('name' => 'SmugMug Media Silo',
			'version' => '0.1',
			'url' => 'http://phpsmug.com/smugmug-silo-plugin/',
			'author' => 'Colin Seymour',
			'authorurl' => 'http://www.colinseymour.co.uk/',
			'license' => 'Apache License 2.0',
			'description' => 'Implements basic SmugMug integration',
			'copyright' => date("Y"),
			);
	}

	/**
	* Initialize some internal values when plugin initializes
	*/
	public function action_init()
	{
				$this->smug = new phpSmug("APIKey={$this->APIKey}", "AppName={$this->info->name}/{$this->info->version}", "OAuthSecret={$this->OAuthSecret}");
				// Enable caching.  This will be for 24 hours, but will be cleared whenever a file is uploaded via this plugin or manually vi the configure options.
				$this->smug->enableCache("type=fs", "cache_dir=". HABARI_PATH . '/user/cache/', "cache_expire=86400");
	}

	/**
	* Return basic information about this silo
	*     name- The name of the silo, used as the root directory for media in this silo
	*	  icon- An icon to represent the silo
	*/
	public function silo_info()
	{
		if($this->is_auth()) {
			return array('name' => self::SILO_NAME, 'icon' => URL::get_from_filesystem(__FILE__) . '/icon.png');
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
		$token = Options::get('smugmugsilo__token_' . User::identify()->id);
		$timeout = Options::get('smugmugsilo__cache_timeout_' . User::identify()->id);

		$token = unserialize($token);
		$this->smug->setToken("id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}");
		$results = array();
		$section = strtok($path, '/');
		switch($section) {
			case 'recentPhotos':
				$photos = self::getFeed(10);
				$i = 0; $ids = array();
				foreach($photos->entry as $photo) {
					$attribs = 	$photo->link->attributes();
					$ids[$i] = (string)$attribs->href;
					$i++;				
				}
				foreach($ids as $photoURL) {
					$idKey = explode('#', $photoURL);
					list($id, $key) = explode('_', $idKey[1]);
					$info = $this->smug->images_getInfo("ImageID={$id}", "ImageKey={$key}", "Extras=Caption,Format,AlbumURL,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL,FileName");
					$props = array('Title' => '', 'FileName' => '');
					foreach($info as $name => $value) {
						$props[$name] = (string)$value;
						$props['filetype'] = 'smugmug';
						if ($name == "Caption") {
							$val = nl2br($value);
							$val = explode('<br />', $val);
							$props['Title'] = $this->truncate($val[0]);
						}
						if ($props['Title'] == '') {
							$props['Title'] = $props['FileName'];
						}
									
						$results[] = new MediaAsset(
							self::SILO_NAME . '/recentPhotos/' . $id,
							false,
							$props
						);
					}
				}
			
				break;
			case 'recentGalleries':
				$selected_gallery = strtok('/');
				$galmeta = explode('_', $selected_gallery);
				if ($selected_gallery) {
					$props = array('Title' => '', 'FileName' => '');
					$photos = $this->smug->images_get("AlbumID={$galmeta[0]}", "AlbumKey={$galmeta[1]}", "Extras=Caption,Format,AlbumURL,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL,FileName"); // Use options to select specific info
					foreach($photos['Images'] as $photo) {
						foreach($photo as $name => $value) {
								$props[$name] = (string)$value;
								$props['filetype'] = 'smugmug';
								// SmugMug has no concept of Titles, so we split the caption by new lines and 
								// truncate the first line to 23 chars and use this for the title.
								// If the title is empty (ie, there's no caption), we rely on the filename.
								if ($name == "Caption") {
									$val = nl2br($value);
									$val = explode('<br />', $val);
									$props['Title'] = $this->truncate($val[0]);
								}
								if ($props['Title'] == '') {
									$props['Title'] = $props['FileName'];
								}
									
						}
						
						$results[] = new MediaAsset(
							self::SILO_NAME . '/photos/' . $photo['id'],
							false,
							$props
						);
					}
				} else {
					$photos = self::getFeed(10, 'Galleries');
					$i = 0; $ids = array();
					foreach($photos->entry as $photo) {
						$attribs = 	$photo->link->attributes();
						$ids[$i] = (string)$attribs->href;
						$i++;				
					}
					$j = 0;
					$galleries = array();
					foreach($ids as $galURL) {
						$idKey = explode('/', $galURL);
						list($id, $key) = explode('_', end($idKey));
						$galleries[$j] = $this->smug->albums_getInfo("AlbumID={$id}", "AlbumKey={$key}");
						$j++;
					}
					foreach($galleries as $gallery) {
						$results[] = new MediaAsset(
							self::SILO_NAME . '/recentGalleries/' . (string)$gallery['id'].'_'.$gallery['Key'],
							true,
							array('title' => (string)$gallery['Title'])
						);
					}
				}
				break;
			case 'galleries':
				$selected_gallery = strtok('/');
				$galmeta = explode('_', $selected_gallery);
				if ($selected_gallery) {
					$props = array('Title' => '', 'FileName' => '');
					$photos = $this->smug->images_get("AlbumID={$galmeta[0]}", "AlbumKey={$galmeta[1]}", "Extras=Caption,Format,AlbumURL,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL,FileName"); // Use options to select specific info
					foreach($photos['Images'] as $photo) {
						foreach($photo as $name => $value) {
								$props[$name] = (string)$value;
								$props['filetype'] = 'smugmug';
								// SmugMug has no concept of Titles, so we split the caption by new lines and 
								// truncate the first line to 23 chars and use this for the title.
								// If the title is empty (ie, there's no caption), we rely on the filename.
								if ($name == "Caption") {
									$val = nl2br($value);
									$val = explode('<br />', $val);
									$props['Title'] = $this->truncate($val[0]);
								}
								if ($props['Title'] == '') {
									$props['Title'] = $props['FileName'];
								}
									
						}
						
						$results[] = new MediaAsset(
							self::SILO_NAME . '/photos/' . $photo['id'],
							false,
							$props
						);
					}
				} else {
					$galleries = $this->smug->albums_get();
					foreach($galleries as $gallery) {
						$results[] = new MediaAsset(
							self::SILO_NAME . '/galleries/' . (string)$gallery['id'].'_'.$gallery['Key'],
							true,
							array('title' => (string)$gallery['Title'])
						);
					}
						
				}
				break;
			case '':
				$results[] = new MediaAsset(
					self::SILO_NAME . '/galleries',
					true,
					array('title' => 'All Galleries')
				);
				$results[] = new MediaAsset(
					self::SILO_NAME . '/recentGalleries',
					true,
					array('title' => 'Recent Galleries')
				);
				$results[] = new MediaAsset(		
					self::SILO_NAME . '/recentPhotos',
					true,
					array('title' => 'Recent Photos')
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
	* Get the direct URL of the file of the specified path
	*
	* @param string $path The path of the file to retrieve
	* @param array $qualities Qualities that specify the version of the file to retrieve.
	* @return string The requested url
	*/
	public function silo_url($path, $qualities = null)
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
		$photos = self::recent(5);
		Utils::debug($photos);
		foreach($photos['photos'] as $photo){
			echo '<img src="' . $url . '" width="150px" alt="' . ( isset( $photo['title'] ) ? $photo['title'] : _t('This photo has no title') ) . '">';
		}
	}


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
	 *
	 * @todo This should really use FormUI, but FormUI needs a way to submit forms via ajax
	 */
	public function filter_media_controls( $controls, $silo, $path, $panelname )
	{
		$class = __CLASS__;
		if($silo instanceof $class) {
			$controls[] = $this->link_panel(self::SILO_NAME . '/' . $path, 'clearCache', _t('ClearCache'));
			if(User::identify()->can('upload_smugmug')) {
				if (strchr($path, '/')) {	
					$controls[] = $this->link_panel(self::SILO_NAME . '/' . $path, 'upload', _t('Upload'));
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
	 *
	 * @todo Move the uploaded file from the temporary location to the location indicated by the path field.
	 */
	public function filter_media_panels( $panel, $silo, $path, $panelname)
	{
		$class = __CLASS__;
		if($silo instanceof $class) {
			switch($panelname) {
				case 'clearCache':
						$this->smug->clearCache();
						Cache::expire(array("smugmug", '*'));	// Assumes ticket #785 has been implemented into Habari
						$panel .= "<div style='width: 200px; margin:10px auto; text-align:center;'><p>Cache Cleared</p>";
						$panel .= '<p><br/><strong><a href="#" onclick="habari.media.forceReload();habari.media.clickdir(\''. self::SILO_NAME . '/'. $path . '\');habari.media.showdir(\''. self::SILO_NAME . '/'. $path . '\');">'._t('Return to current silo path.').'</a></strong></p></div>';
					break;
				case 'upload':
					if(isset($_FILES['file'])) {
						$AlbumID = intval($_POST['AlbumID']);
						$Caption = $_POST['Caption'];
						
						try {
							$result = $this->smug->images_upload("AlbumID={$AlbumID}", "File={$_FILES['file']['tmp_name']}", "FileName={$_FILES['file']['name']}", "Caption={$_POST['Caption']}", "Keywords={$_POST['Keywords']}");
							$img = $this->smug->images_getURLs("ImageID={$result['id']}", "ImageKey={$result['Key']}");
							$output = "<p><img src={$img['TinyURL']} /></p>";
							$output .= "<p>Image successfully uploaded</p>";
						}
						catch (Exception $e) {
							$output = $e->getMessage();
						}
						
						$this->smug->clearCache();
						Cache::expire(array("smugmug", '*'));	// Assumes ticket #785 has been implemented into Habari
						$panel .= "<div style='width: 200px; margin:10px auto; text-align:center;'>{$output}";
						$panel .= '<p><br/><strong><a href="#" onclick="habari.media.forceReload();habari.media.clickdir(\''. self::SILO_NAME . '/'. $path . '\');habari.media.showdir(\''. self::SILO_NAME . '/'. $path . '\');">Browse the current silo path.</a></strong></p></div>';
					}
					else {
						$fullpath = self::SILO_NAME . '/' . $path;
						$form_action = URL::get('admin_ajax', array('context' => 'media_panel'));
						$gal = explode('/', $path);
						$gal = explode('_', $gal[1]);
						$gallery = $this->smug->albums_getInfo("AlbumID={$gal[0]}", "AlbumKey={$gal[1]}");
						$panel.= <<< UPLOAD_FORM
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
<tr><td colspan="2" style="text-align:right;padding-top: 5px;"><input type="submit" name="upload" value="Upload" onclick="spinner.start();" /></td></tr>
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
	public function filter_plugin_config($actions, $plugin_id)
	{
		if ($plugin_id == $this->plugin_id()){
			$phpSmug_ok = $this->is_auth();

			if($phpSmug_ok){
				$actions[] = _t('Configure');
				$actions[] = _t('De-Authorize');
			} else {
				$actions[] = _t('Authorize');
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
	public function action_plugin_ui($plugin_id, $action)
	{
		if ($plugin_id == $this->plugin_id()){
			switch ($action){
				case _t('Authorize'):
					if($this->is_auth()){
						$deauth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'De-Authorize')) . '#plugin_options';
						echo '<p>'._t('You have already successfully authorized Habari to access your SmugMug account').'.</p>';
						echo '<p>'._t('Do you want to ')."<a href=\"{$deauth_url}\">"._t('revoke authorization').'</a>?</p>';
					} else {
						$reqToken = $this->smug->auth_getRequestToken();
						$_SESSION['SmugGalReqToken'] = serialize($reqToken);
						
						$confirm_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm')) . '#plugin_options';
						echo '<form><p>'._t('To use this plugin, you must authorize Habari to have access to your SmugMug account').".";
						echo "<button style='margin-left:10px;' onclick=\"window.open('{$this->smug->authorize("Access=Public", "Permissions=Modify")}', '_blank').focus();return false;\">"._t('Authorize')."</button></p>";
						echo '<p>'._t('When you have completed the authorization on SmugMug, return here and confirm that the authorization was successful.');
						echo "<button style='margin-left:10px;' onclick=\"location.href='{$confirm_url}'; return false;\">"._t('Confirm')."</button></p>";
						echo '</form>';
					}
					break;

				case 'confirm':
					if(!isset($_SESSION['SmugGalReqToken'])){
						$auth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize')) . '#plugin_options';
						echo '<form><p>'._t('Either you have already authorized Habari to access your SmugMug account, or you have not yet done so.  Please').'<strong><a href="' . $auth_url . '">'._t('try again').'</a></strong>.</p></form>';
					} else {
						$reqToken = unserialize($_SESSION['SmugGalReqToken']);
						$this->smug->setToken("id={$reqToken['id']}", "Secret={$reqToken['Secret']}");
						$token = $this->smug->auth_getAccessToken();
						
						// Lets speed things up a bit by pre-fetching the album list so it's in our cache
						$this->smug->setToken("id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}");
						$this->smug->albums_get();
						
						$config_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Configure')) . '#plugin_options';

						if(isset($token)){
							Options::set('smugmugsilo__token_' . User::identify()->id, serialize($token));
							Options::set('smugmugsilo__nickName_'. User::identify()->id, $token['User']['NickName']);
							EventLog::log(_t('Authorization Confirmed.'));
							echo '<form><p>'._t('Your authorization was set successfully. You can now <b><a href="'.$config_url.'">configure</a></b> the SmugMug Silo to suit your needs.').'</p></form>';
						}
						else{
							echo '<form><p>'._t('There was a problem with your authorization:').'</p></form>';
						}
						unset($_SESSION['SmugGalReqToken']);
					}
					break;
				case _t('De-Authorize'):
					Options::set('smugmugsilo__token_' . User::identify()->id);
					$reauth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize')) . '#plugin_options';
					echo '<form><p>'._t('The SmugMug Silo Plugin authorization has been deleted. Please ensure you revoke access from your SmugMug Control Panel too.').'<p>';
					echo "<p>"._t('Do you want to ')."<b><a href=\"{$reauth_url}\">"._t('re-authorize this plugin')."</a></b>?<p></form>";
					EventLog::log(_t('De-authorized'));

					break;
				case _t('Configure') :
					$token = Options::get('smugmugsilo__token_' . User::identify()->id);
					$customSize = Options::get('smugmugsilo__custom_size_' . User::identify()->id);
					$imageSize = Options::get('smugmugsilo__image_size_' . User::identify()->id);
					$useTB = Options::get('smugmugsilo__use_thickbox_' . User::identify()->id);
					
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$ui->append( 'select', 'image_size','option:smugmugsilo__image_size_' . User::identify()->id, _t( 'Default size for images in Posts:' ) );
					$ui->append( 'text', 'custom_size', 'option:smugmugsilo__custom_size_' . User::identify()->id, _t('Custom Size of Longest Edge (px):') );
					if ($imageSize != 'Custom') {
						$ui->custom_size->class = 'formcontrol hidden';
					}
					$ui->image_size->options = array( 'Ti' => 'Tiny', 'Th' => 'Thumbnail', 'S' => 'Small', 'M' => 'Medium', 'L' => 'Large (if available)', 'XL' => 'XLarge (if available)', 'X2' => 'X2Large (if available)', 'X3' => 'X3Large (if available)', 'O' => 'Original (if available)', 'Custom' => 'Custom (Longest edge in px)' );
					// If Thickbox enabled, give option of using it, and what img size to show:
					if (Plugins::is_loaded('Thickbox')) { // Requires svn r2903 or later due to ticket #754
						$ui->append('fieldset', 'tbfs', 'ThickBox');
						$ui->tbfs->append('label', 'tbfs', _t("You have the Thickbox plugin installed and active, so you can take advantage of it's functionality with the SmugMug Silo if you wish."));
						$ui->tbfs->append('checkbox', 'use_tb', 'option:smugmugsilo__use_thickbox_' . User::identify()->id, _t('Use Thickbox?'));
						$ui->tbfs->append('select', 'tb_image_size', 'option:smugmugsilo__thickbox_img_size_' . User::identify()->id, _t('Image size to use for Thickbox (warning: large images are slow to load):'));
						if ($useTB == FALSE) {
							$ui->tb_image_size->class = 'formcontrol hidden';
						}
						$ui->tbfs->tb_image_size->options = array( 'MediumURL' => 'Medium', 'LargeURL' => 'Large (if available)', 'XLargeURL' => 'XLarge (if available)', 'X2LargeURL' => 'X2Large (if available)', 'X3LargeURL' => 'X3Large (if available)', 'OriginalURL' => 'Original (if available)' );
					} 
					//$photos = self::recent(5);
					//Utils::debug($photos->channel->item[1]);
					$ui->append('submit', 'save', _t( 'Save Options' ) );
					$ui->set_option('success_message', _t('Options successfully saved.'));
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
					$this->smug->clearCache();	
					rmdir($this->smug->cache_dir);
					Cache::expire(array("smugmug", '*'));	// Assumes ticket #785 has been implemented into Habari
			}
	}

	/**
	 * Add custom styling and Javascript controls to the footer of the admin interface
	 **/
	public function action_admin_footer( $theme ) {
		if(Controller::get_var('page') == 'publish') {
			$size = Options::get('smugmugsilo__image_size_' . User::identify()->id);
			if ($size == "Custom") {
				$customSize = Options::get('smugmugsilo__custom_size_' . User::identify()->id);
				$size = "{$customSize}x{$customSize}";
			}
			$nickName = Options::get('smugmugsilo__nickName_'. User::identify()->id);
			$useThickBox = Options::get('smugmugsilo__use_thickbox_' . User::identify()->id);
			$thickBoxSize = Options::get('smugmugsilo__thickbox_img_size_' . User::identify()->id);
			
			echo <<< SMUGMUG_ENTRY_CSS_1
			<style type="text/css">
			div.smugmug ul.mediaactions.dropbutton li { display: inline !important; float:left; width:20px; }
			div.smugmug ul.mediaactions.dropbutton li.first-child a { background: none !important; }
			div.smugmug ul.mediaactions.dropbutton li.first-child:hover { -moz-border-radius-bottomleft: 3px !important;  -webkit-border-bottom-left-radius: 3px !important; }
			div.smugmug ul.mediaactions.dropbutton li.last-child:hover { -moz-border-radius-bottomleft: 0px !important;  -webkit-border-bottom-left-radius: 0px !important; }
			div.smugmug .mediaphotos > ul li { min-width:5px !important; width:9px !important;}
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

if ($useThickBox && Plugins::is_loaded('Thickbox')) {
	echo "habari.editor.insertSelection('<a class=\"thickbox\" href=\"' + fileobj.{$thickBoxSize} + '\" title=\"'+ fileobj.Caption + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\"></a>');";
} else {
	echo "habari.editor.insertSelection('<a href=\"' + fileobj.AlbumURL + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" title=\"'+ fileobj.Caption + '\"></a>');";
}

echo <<< SMUGMUG_ENTRY_CSS_2

				}

				habari.media.preview.smugmug = function(fileindex, fileobj) {
					return '<div class="mediatitle"><a href="' + fileobj.AlbumURL + '" class="medialink">media</a>' + fileobj.Title + '</div><img src="' + fileobj.ThumbURL + '">';
				}
			</script>
SMUGMUG_ENTRY_CSS_2;
		}
		// Javascript required for config panel
		if (Controller::get_var('configure') == $this->plugin_id) {
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
	
	/**
	 * Check if the application has been authorised to access SmugMug
	 **/
	private function is_auth()
	{
		static $phpSmug_ok = null;
		if(isset($phpSmug_ok)){
			return $phpSmug_ok;
		}

		$phpSmug_ok = false;
		$token = Options::get('smugmugsilo__token_' . User::identify()->id);
		$token = unserialize($token);

		if($token != ''){
			$this->smug->setToken("id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}");
			$result = $this->smug->auth_checkAccessToken();
			if(isset($result)){
				$phpSmug_ok = true;
			}
			else{
				Options::set('smugmugsilo__token_' . User::identify()->id);
				unset($_SESSION['smugmug_token']);
			}
		}
		return $phpSmug_ok;
	}
	/**
	 * Function to truncate strings to a set number of characters (23 by default)
	 * This is used to truncate the "Title" when displaying the images in the silo
	 **/
	private function truncate($string, $max = 23, $replacement = '...')
	{
		if (strlen($string) <= $max)
		{
			return $string;
		}
		$leave = $max - strlen($replacement);
		return substr_replace($string, $replacement, $leave);
	}

	
	private function getFeed($num = 10, $type = "Photos", $keyword = NULL) {
		/* Testing getting the most recent $num photos using the ATOM feed (bit of a fudge)
		   We get the list of recent photos/albums from the atom feed as the API doesn't offer this
		   functionality.  We then use the API to get the relevant information
		   
		   ToDo: Implement native Habari caching for this call.  Need to see if I can set a cache timeout. Something like 1 hour should do.
		 */
		$nickName = Options::get('smugmugsilo__nickName_'. User::identify()->id);
		//$nickname = 'colinseymour';
		switch($type) {
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
		$this->cache_name = (array("smugmug", "{$urlEnd}". strtolower(get_class($this))));
		if (Cache::has($this->cache_name)) {
			$response = Cache::get($this->cache_name);
		} else {
			$call = new RemoteRequest($url);
			$call->set_timeout(5);
			$result = $call->execute();
			if (Error::is_error($result)){
				throw $result;
			}
			$response = $call->get_response_body();
			Cache::set($this->cache_name, $response);
		}
		
		try{
			$xml = new SimpleXMLElement($response);
			return $xml;
		}
		catch(Exception $e) {
			Session::error('Currently unable to connect to SmugMug.', 'SmugMug API');
//				Utils::debug($url, $response);
			return false;
		}
	}
}

?>
