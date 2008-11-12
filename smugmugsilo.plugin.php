<?php
/**
* SmugMug Silo
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
		$flickr = new Flickr();
		$results = array();
		$size = Options::get('flickrsilo__flickr_size');

		$section = strtok($path, '/');
		switch($section) {
			case 'photos':
				$xml = $flickr->photosSearch();
				foreach($xml->photos->photo as $photo) {

					$props = array();
					foreach($photo->attributes() as $name => $value) {
						$props[$name] = (string)$value;
					}
					$props['url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}$size.jpg";
					$props['thumbnail_url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_m.jpg";
					$props['flickr_url'] = "http://www.flickr.com/photos/{$_SESSION['nsid']}/{$photo['id']}";
					$props['filetype'] = 'flickr';

					$results[] = new MediaAsset(
						self::SILO_NAME . '/photos/' . $photo['id'],
						false,
						$props
					);
				}
				break;
			case 'videos':
				$xml = $flickr->videoSearch();
				foreach($xml->photos->photo as $photo) {

					$props = array();
					foreach($photo->attributes() as $name => $value) {
						$props[$name] = (string)$value;
					}
					$props['url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}$size.jpg";
					$props['thumbnail_url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_m.jpg";
					$props['flickr_url'] = "http://www.flickr.com/photos/{$_SESSION['nsid']}/{$photo['id']}";
					$props['filetype'] = 'flickrvideo';

					$results[] = new MediaAsset(
						self::SILO_NAME . '/photos/' . $photo['id'],
						false,
						$props
					);
				}
				break;
			case 'tags':
				$selected_tag = strtok('/');
				if($selected_tag) {
					$xml = $flickr->photosSearch(array('tags'=>$selected_tag));
					foreach($xml->photos->photo as $photo) {

						$props = array();
						foreach($photo->attributes() as $name => $value) {
							$props[$name] = (string)$value;
						}
						$props['url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}.jpg";
						$props['thumbnail_url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_m.jpg";
						$props['flickr_url'] = "http://www.flickr.com/photos/{$_SESSION['nsid']}/{$photo['id']}";
						$props['filetype'] = 'flickr';

						$results[] = new MediaAsset(
							self::SILO_NAME . '/photos/' . $photo['id'],
							false,
							$props
						);
					}
				}
				else {
					$xml = $flickr->tagsGetListUser($_SESSION['nsid']);
					foreach($xml->who->tags->tag as $tag) {
						$results[] = new MediaAsset(
							self::SILO_NAME . '/tags/' . (string)$tag,
							true,
							array('title' => (string)$tag)
						);
					}
				}
				break;
			case 'sets':
				$selected_set = strtok('/');
				if($selected_set) {
					$xml = $flickr->photosetsGetPhotos($selected_set);
					foreach($xml->photoset->photo as $photo) {

						$props = array();
						foreach($photo->attributes() as $name => $value) {
							$props[$name] = (string)$value;
						}
						$props['url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}.jpg";
						$props['thumbnail_url'] = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_m.jpg";
						$props['flickr_url'] = "http://www.flickr.com/photos/{$_SESSION['nsid']}/{$photo['id']}";
						$props['filetype'] = 'flickr';

						$results[] = new MediaAsset(
							self::SILO_NAME . '/photos/' . $photo['id'],
							false,
							$props
						);
					}
				}
				else {
					$xml = $flickr->photosetsGetList($_SESSION['nsid']);
					foreach($xml->photosets->photoset as $set) {
						$results[] = new MediaAsset(
							self::SILO_NAME . '/sets/' . (string)$set['id'],
							true,
							array('title' => (string)$set->title)
						);
					}
				}
				break;


			case '':
				$results[] = new MediaAsset(
					self::SILO_NAME . '/photos',
					true,
					array('title' => 'Photos')
				);
				$results[] = new MediaAsset(
					self::SILO_NAME . '/videos',
					true,
					array('title' => 'Videos')
				);
				$results[] = new MediaAsset(
					self::SILO_NAME . '/tags',
					true,
					array('title' => 'Tags')
				);
				$results[] = new MediaAsset(
					self::SILO_NAME . '/sets',
					true,
					array('title' => 'Sets')
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
		$photo = false;
		if(preg_match('%^photos/(.+)$%', $path, $matches)) {
			$id = $matches[1];
			$photo = self::$cache[$id];
		}

		$size = '';
		if(isset($qualities['size']) && $qualities['size'] == 'thumbnail') {
			$size = '_m';
		}
		$url = "http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}{$size}.jpg";
		return $url;
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
		$flickr = new Flickr();
		$token = Options::get('flickr_token_' . User::identify()->id);
		$result = $flickr->call('flickr.auth.checkToken',
			array('api_key' => $flickr->key,
				'auth_token' => $token));
		$photos = $flickr->GetPublicPhotos($result->auth->user['nsid'], null, 5);
		foreach($photos['photos'] as $photo){
			$url = $flickr->getPhotoURL($photo);
			echo '<img src="' . $url . '" width="150px" alt="' . ( isset( $photo['title'] ) ? $photo['title'] : _t('This photo has no title') ) . '">';
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
				$actions[] = _t('De-Authorize');
			} else {
				$actions[] = _t('Authorize');
			}
			$actions[] = _t('Configure');
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
						echo "<p>You have already successfully authorized Habari to access your SmugMug account.</p>";
						echo "<p>Do you want to <a href=\"\">revoke authorization</a>?</p>";
					} else {
						
						$smug = new phpSmug("APIKey={$this->APIKey}", "AppName=SmugMug Silo For Habari/0.1", "OAuthSecret={$this->OAuthSecret}");
						
						$reqToken = $smug->auth_getRequestToken();
						$_SESSION['SmugGalReqToken'] = serialize($reqToken);
						
						$confirm_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm')) . '#plugin_options';
						echo "<form><p>"._t('To use this plugin, you must authorize Habari to have access to your SmugMug account').".";
						echo "<button style='margin-left:10px;' onclick=\"window.open('{$smug->authorize()}', '_blank').focus();return false;\">"._t('Authorize')."</button></p>";
						echo "<p>"._t('When you have completed the authorization on SmugMug, return here and confirm that the authorization was successful.');
						echo "<button style='margin-left:10px;' onclick=\"location.href='{$confirm_url}'; return false;\">"._t('Confirm')."</button></p>";
						echo "</form>";
					}
					break;

				case _t('confirm'):
					if(!isset($_SESSION['SmugGalReqToken'])){
						$auth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize')) . '#plugin_options';
						echo '<form><p>'._t('Either you have already authorized Habari to access your SmugMug account, or you have not yet done so.  Please').'<strong><a href="' . $auth_url . '">'._t('try again').'</a></strong>.</p></form>';
					} else {
						$smug = new phpSmug("APIKey={$this->APIKey}", "AppName=SmugMug Silo For Habari/0.1", "OAuthSecret={$this->OAuthSecret}");
						
						$reqToken = unserialize($_SESSION['SmugGalReqToken']);
						$smug->setToken("id={$reqToken['id']}", "Secret={$reqToken['Secret']}");
						$token = $smug->auth_getAccessToken();
						if(isset($token)){
							Options::set('smugmug_token_' . User::identify()->id, '' . serialize($token));
							echo '<form><p>'._t('Your authorization was set successfully. You can now configure the SmugMug Silo to suit your needs.').'</p></form>';
						}
						else{
							echo '<form><p>'._t('There was a problem with your authorization:').'</p></form>';
						}
						unset($_SESSION['SmugGalReqToken']);
					}
					break;
				case _t('De-Authorize'):
					Options::set('smugmug_token_' . User::identify()->id);
					$reauth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize')) . '#plugin_options';
					echo '<form><p>'._t('The SmugMug Silo Plugin authorization has been deleted. Please ensure you revoke access from your SmugMug Control Panel too.').'<p>';
					echo "<p>"._t('Do you want to ')."<b><a href=\"{$reauth_url}\">"._t('re-authorize this plugin')."</a></b>?<p></form>";
					break;
				case _t('Configure') :
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$ui->append( 'select', 'smugmug_size','option:smugmugsilo__smugmug_size', _t( 'Default size for images in Posts:' ) );
					// Maybe give people the choice at selection time
					//$ui->flickr_size->options = array( 'TinyURL' => 'Tiny', 'ThumbURL' => 'Thumbnail', 'SmallURL' => 'Small (240px)', 'MediumURL' => 'Medium (500px)', 'LargeURL' => 'Large (1024px)', 'XLargeURL' => 'XLarge', 'XLargeURL' => 'XLarge', 'XLargeURL' => 'XLarge', 'OriginalURL' => 'Original' );
					$ui->append('submit', 'save', _t( 'Save' ) );
					$ui->set_option('success_message', _t('Options saved'));
					$ui->out();
					break;
			}
		}
	}
	
	public function action_admin_footer( $theme ) {
		if(Controller::get_var('page') == 'publish') {
			$size = Options::get('flickrsilo__flickr_size');
			switch($size) {
				case '_s':
					$vsizex = 75;
					break;
				case '_t':
					$vsizex = 100;
					break;
				case '_m':
					$vsizex = 240;
					break;
				case '':
					$vsizex = 500;
					break;
				case '_b':
					$vsizex = 1024;
					break;
				case '_o':
					$vsizex = 400;
					break;
			}
			$vsizey = intval($vsizex/4*3);


			echo <<< FLICKR
			<script type="text/javascript">
				habari.media.output.flickr = {display: function(fileindex, fileobj) {
					habari.editor.insertSelection('<a href="' + fileobj.flickr_url + '"><img src="' + fileobj.url + '"></a>');
				}}
				habari.media.output.flickrvideo = {
					embed_video: function(fileindex, fileobj) {
						habari.editor.insertSelection('<object type="application/x-shockwave-flash" width="{$vsizex}" height="{$vsizey}" data="http://www.flickr.com/apps/video/stewart.swf?v=49235" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"> <param name="flashvars" value="intl_lang=en-us&amp;photo_secret=' + fileobj.secret + '&amp;photo_id=' + fileobj.id + '&amp;show_info_box=true"></param> <param name="movie" value="http://www.flickr.com/apps/video/stewart.swf?v=49235"></param> <param name="bgcolor" value="#000000"></param> <param name="allowFullScreen" value="true"></param><embed type="application/x-shockwave-flash" src="http://www.flickr.com/apps/video/stewart.swf?v=49235" bgcolor="#000000" allowfullscreen="true" flashvars="intl_lang=en-us&amp;photo_secret=' + fileobj.secret + '&amp;photo_id=' + fileobj.id + '&amp;flickr_show_info_box=true" height="{$vsizey}" width="{$vsizex}"></embed></object>');
					},
					thumbnail: function(fileindex, fileobj) {
						habari.editor.insertSelection('<a href="' + fileobj.flickr_url + '"><img src="' + fileobj.url + '"></a>');
					}
				}
				habari.media.preview.flickr = function(fileindex, fileobj) {
					var stats = '';
					return '<div class="mediatitle"><a href="' + fileobj.flickr_url + '" class="medialink">media</a>' + fileobj.title + '</div><img src="' + fileobj.thumbnail_url + '"><div class="mediastats"> ' + stats + '</div>';
				}
				habari.media.preview.flickrvideo = function(fileindex, fileobj) {
					var stats = '';
					return '<div class="mediatitle"><a href="' + fileobj.flickr_url + '" class="medialink">media</a>' + fileobj.title + '</div><img src="' + fileobj.thumbnail_url + '"><div class="mediastats"> ' + stats + '</div>';
				}
			</script>
FLICKR;
		}
	}

	private function is_auth()
	{
		static $phpSmug_ok = null;
		if(isset($phpSmug_ok)){
			return $phpSmug_ok;
		}

		$phpSmug_ok = false;
		$token = Options::get('smugmug_token_' . User::identify()->id);
		/*
		$token = unserialize($token);



		if($token != ''){
			include dirname(__FILE__).'/phpSmug/phpSmug.php';
			$t = new phpSmug("APIKey=woTP74YfM4zRoScpGFdHYPMLRYZSEhl2", "AppName=SmugMug Silo For Habari/0.1", "OAuthSecret=5a3707ce2c2afadaa5a5e0c1c327ccae");
			$t->setToken("id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}");
			$result = $t->auth_checkAccessToken();
			if(isset($result)){
				$phpSmug_ok = true;
			}
			else{
				Options::set('smugmug_token_' . User::identify()->id);
				unset($_SESSION['smugmug_token']);
			}
		}
		*/
		/*
		 */
		if ($token != '') {
			$phpSmug_ok = true;
		}

		return $phpSmug_ok;
	}
}

?>
