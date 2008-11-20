<?php
/**
* SmugMug Silo
*
* TODO: 
*	- Implement upload functionality
*	- Sort out cache clear up, but need to fix phpSmug first
*	- Add "clear cache" button
*	- Think about offering galleries/categories/latest etc when initially opening silo
*/

require_once(dirname(__FILE__).'/phpSmug/phpSmug.php');

class SmugMugSilo extends Plugin implements MediaSilo
{
	const SILO_NAME = 'SmugMug';
	var $APIKey = 'woTP74YfM4zRoScpGFdHYPMLRYZSEhl2';
	var $OAuthSecret = '5a3707ce2c2afadaa5a5e0c1c327ccae';

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

		$this->smug->enableCache("type=fs", "cache_dir=". HABARI_PATH . '/user/cache/', "cache_expire={$timeout}");
		$token = unserialize($token);
		$this->smug->setToken("id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}");
		$results = array();
		$section = strtok($path, '/');
		switch($section) {
			case 'categories':
				$categories = $this->smug->categories_get();
				foreach($categories as $category) {
					$results[] = new MediaAsset(
						self::SILO_NAME . '/categories/' . (string)$category['Name'],
						true,
						array('title' => (string)$category['Name'])
					);
				}
				break;
			case 'galleries':
				$selected_gallery = strtok('/');
				$galmeta = explode('_', $selected_gallery);
				if ($selected_gallery) {
					$props = array();
					$photos = $this->smug->images_get("AlbumID={$galmeta[0]}", "AlbumKey={$galmeta[1]}", "Extras=Caption,Format,AlbumURL,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL"); // Use options to select specific info
					foreach($photos['Images'] as $photo) {
						foreach($photo as $name => $value) {
								$props[$name] = (string)$value;
								$props['filetype'] = 'smugmug';
								if ($name == "Caption") {
									$val = nl2br($value);
									$val = explode('<br />', $val);
									$props['Title'] = $this->truncate($val[0]);
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
					array('title' => 'Galleries')
				);
				$results[] = new MediaAsset(
					self::SILO_NAME . '/categories',
					true,
					array('title' => 'Categories')
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
						$this->smug->enableCache("type=fs", "cache_dir=". HABARI_PATH . '/user/cache/');	// Leaves with the default cache time
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
					$ui->append( 'text', 'cache_timeout', 'option:smugmugsilo__cache_timeout_' . User::identify()->id, _t( 'Cache timeout (seconds):'));
					$ui->cache_timeout->value = '3600';
					// Todo: Add "clear cache" button
					// Todo: Clear cache when settings saved.
					// Maybe give people the choice at selection time
					$ui->image_size->options = array( 'Ti' => 'Tiny', 'Th' => 'Thumbnail', 'S' => 'Small', 'M' => 'Medium', 'L' => 'Large (if available)', 'XL' => 'XLarge (if available)', 'X2' => 'X2Large (if available)', 'X3' => 'X3Large (if available)', 'O' => 'Original (if available)', 'Custom' => 'Custom (Longest edge in px)' );
					// If Thickbox enabled, give option of using it, and what img size to show:
					if (Plugins::is_loaded('Thickbox')) { // Bug here - this always returns true if plugin exist, even if plugin is disabled - ticket 754 logged
						$ui->append('fieldset', 'tbfs', 'ThickBox');
						$ui->tbfs->append('label', 'tbfs', _t("You have the Thickbox plugin installed and active, so you can take advantage of it's functionality with the SmugMug Silo if you wish."));
						$ui->tbfs->append('checkbox', 'use_tb', 'option:smugmugsilo__use_thickbox_' . User::identify()->id, _t('Use Thickbox?'));
						$ui->tbfs->append('select', 'tb_image_size', 'option:smugmugsilo__thickbox_img_size_' . User::identify()->id, _t('Image size to use for Thickbox (warning: large images are slow to load):'));
						if ($useTB == FALSE) {
							$ui->tb_image_size->class = 'formcontrol hidden';
						}
						$ui->tbfs->tb_image_size->options = array( 'MediumURL' => 'Medium', 'LargeURL' => 'Large (if available)', 'XLargeURL' => 'XLarge (if available)', 'X2LargeURL' => 'X2Large (if available)', 'X3LargeURL' => 'X3Large (if available)', 'OriginalURL' => 'Original (if available)' );
					}
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
			// Was this plugin deactivated?
			if ( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
					$this->smug->cache = 'fs';
					$this->smug->cache_dir = HABARI_PATH . '/user/cache/';
					$this->smug->clearCache();					
					EventLog::log(_t('SmugMug Silo Cache Cleared.'));
			}
	}

	
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

if ($useThickBox) {
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
	
	private function truncate($string, $max = 23, $replacement = '...')
	{
		if (strlen($string) <= $max)
		{
			return $string;
		}
		$leave = $max - strlen($replacement);
		return substr_replace($string, $replacement, $leave);
	}

	
	private function recent($num = 10) {
		// Testing getting the most recent $num photos using the RSS feed (bit of a fudge)
		$nickname = 'colinseymour';
		$url = "http://api.smugmug.com/hack/feed.mg?Type=nicknameRecent&Data={$nickname}&format=rss200&ImageCount={$num}";
		$call = new RemoteRequest($url);
		$call->set_timeout(5);
		$result = $call->execute();
		if (Error::is_error($result)){
			throw $result;
		}
		$response = $call->get_response_body();
		try{
			$xml = new SimpleXMLElement($response);
			return $xml;
		}
		catch(Exception $e) {
			Session::error('Currently unable to connect to Flickr.', 'flickr API');
//				Utils::debug($url, $response);
			return false;
		}
	}
}

?>
