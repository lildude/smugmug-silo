<?php
/**
 *
 * Copyright 2009 Colin Seymour - http://www.lildude.co.uk/projects/smugmug-media-silo-plugin
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
 * @TODO: Implement complete multi-user support - need to workout how Habari does this first ;-)
 * 
 */

class SmugMugSilo extends Plugin implements MediaSilo
{
    const SILO_NAME = 'SmugMug';

    const APIKEY = '2OSeqHatM6uOQghQssLtkaBUcc9TpLq8';
    const OAUTHSECRET = 'afc04b3e9650cd342fc91072d939405d';
    const CACHE_EXPIRY = 86400;	// seconds.  This is 24 hours.

    private $status;

    /**
    * Respond to the user selecting an action on the plugin page
    *
    * @param string $plugin_id The string id of the acted-upon plugin
    * @param string $action The action string supplied via the filter_plugin_config hook
    */
    public function action_plugin_ui_authorise()
    {
		$this->phpSmugInit();
		if( $this->is_auth() ){
			$deauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'deuthorize' ) ) . '#plugin_options';
			echo '<p>'._t( 'You have already successfully authorized Habari to access your SmugMug account' ).'.</p>';
			echo '<p>'._t( 'Do you want to ' )."<a href=\"{$deauth_url}\">"._t( 'revoke authorization' ).'</a>?</p>';
		}
		else {
			try {
				if ($this->smug->mode == 'read-only') {
					echo '<form><p>'._t('SmugMug is currently in read-only mode, so authorization is not possible. Please try again later.').'</p></form>';
				} else {
					$reqToken = $this->smug->auth_getRequestToken();
					$_SESSION['SmugGalReqToken'] = serialize( $reqToken );
					$confirm_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm' ) ) . '#plugin_options';
					echo '<form><table style="border-spacing: 5px; width: 100%;"><tr><td>'._t( 'To use this plugin, you must authorize Habari to have access to your SmugMug account' ).".</td>";
					echo "<td><button id='auth' style='margin-left:10px;' onclick=\"window.open('{$this->smug->authorize( "Access=Full", "Permissions=Modify" )}', '_blank').focus();return false;\">"._t( 'Authorize' )."</button></td></tr>";
					echo '<tr><td>'._t( 'When you have completed the authorization on SmugMug, return here and confirm that the authorization was successful.' )."</td>";
					echo "<td><button disabled='true' id='conf' style='margin-left:10px;' onclick=\"location.href='{$confirm_url}'; return false;\">"._t( 'Confirm' )."</button></td></tr>";
					echo '</table></form>';
				}
			}
			catch ( Exception $e ) {
				if ( $e->getCode() == 64 || $e->getCode() == 500 ) {
					$msg = 'Unable to communicate with SmugMug. Maybe it\'s down';
				} else {
					$msg = $e->getMessage();
				}
				echo "<br /><p>Ooops. There was a problem: <strong>{$msg}</strong>. <a href='http://status.smugmug.com/'>Check SmugMug notices</a>.</p>";
			}
		}
	}

	public function action_plugin_ui_confirm()
	{
		if( !isset( $_SESSION['SmugGalReqToken'] ) ){
			$auth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'authorize' ) ) . '#plugin_options';
			echo '<form><p>'._t( 'Either you have already authorized Habari to access your SmugMug account, or you have not yet done so.  Please' ).'<strong><a href="' . $auth_url . '">'._t( 'try again' ).'</a></strong>.</p></form>';
		}
		else {
			$this->phpSmugInit();
			$reqToken = unserialize( $_SESSION['SmugGalReqToken'] );
			$this->smug->setToken( "id={$reqToken['Token']['id']}", "Secret={$reqToken['Token']['Secret']}" );
			$token = $this->smug->auth_getAccessToken();

			// Lets speed things up a bit by pre-fetching all the gallery info and caching it
			$this->smug->setToken( "id={$token['Token']['id']}", "Secret={$token['Token']['Secret']}" );
			$this->smug->albums_get();

			$config_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'configure' ) ) . '#plugin_options';

			if( isset( $token ) ){
				$user = User::identify();
				$user->info->smugmugsilo__token = $token;
				$user->info->smugmugsilo__nickName = $token['User']['NickName'];
				// Set required default config options at the same time - the others are really optional.
				$user->info->smugmugsilo__image_size = 'S';
				$user->info->smugmugsilo__link_to = 'nothing';

				$user->info->commit();
				Session::notice( _t( 'Authorization Confirmed.' ) );
				echo '<form><p>'._t( 'Your authorization was set successfully. You can now <b><a href="'.$config_url.'">configure</a></b> the SmugMug Silo to suit your needs.' ).'</p></form>';
			}
			else{
				echo '<form><p>'._t( 'There was a problem with your authorization:' ).'</p></form>';
			}
			unset( $_SESSION['SmugGalReqToken'] );
		}
	}

	public function action_plugin_ui_deauthorise()
	{
		User::identify()->info->smugmugsilo__token = '';
		User::identify()->info->commit();
		// Clear the cache
		$this->clearCaches();
		$reauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'authorize' ) ) . '#plugin_options';
		echo '<form><p>'._t( 'The SmugMug Silo Plugin authorization has been deleted. Please ensure you revoke access from your SmugMug Control Panel too.' ).'<p>';
		echo "<p>"._t( 'Do you want to ' )."<b><a href=\"{$reauth_url}\">"._t( 're-authorize this plugin' )."</a></b>?<p></form>";
		Session::notice( _t( 'De-authorized' ) );
	}

	public function action_plugin_ui_configure()
	{
		$this->add_template( 'smugmugsilo_text', dirname( __FILE__ ) . '/lib/formcontrols/smugmugsilo_text.php' );
		$this->add_template( 'smugmugsilo_select', dirname( __FILE__ ) . '/lib/formcontrols/smugmugsilo_select.php' );
		$this->add_template( 'smugmugsilo_radio', dirname( __FILE__ ) . '/lib/formcontrols/smugmugsilo_radio.php' );

		$user = User::identify();
		$token = $user->info->smugmugsilo__token;
		$customSize = $user->info->smugmugsilo__custom_size;
		$imageSize = $user->info->smugmugsilo__image_size;
		$imgSizes = array( 'Ti' => _t( 'Tiny' ), 'Th' => _t( 'Thumbnail' ), 'S' => _t( 'Small' ), 'M' => _t( 'Medium' ), 'L' => _t( 'Large (if available)' ), 'XL' => _t( 'XLarge (if available)' ), 'X2' => _t( 'X2Large (if available)' ), 'X3' => _t( 'X3Large (if available)' ), 'O' => _t( 'Original (if available)' ), 'Custom' => _t( 'Custom (Longest edge in px)' ) );

		$ui = new FormUI( strtolower( __CLASS__ ) );
		$ui->append( 'fieldset', 'pfs', _t( 'Photo Options: ' ) );
		$ui->pfs->append( 'select', 'image_size','user:smugmugsilo__image_size', _t( 'Default size for images in Posts:' ) );
			$ui->pfs->image_size->template = 'smugmugsilo_select';
		$ui->pfs->append( 'text', 'custom_size', 'user:smugmugsilo__custom_size', _t( 'Custom Size of Longest Edge (px):' ), 'smugmugsilo_text' );
		if ( $imageSize != 'Custom' ) {
			$ui->pfs->custom_size->class = 'formcontrol hidden';
		}
		$ui->pfs->image_size->options = $imgSizes;

		$link_to_array = array (
								'nothing' => _t( 'Nothing'),
								'image' => _t( 'Larger Image'),
								'smugmug' => _t( 'SmugMug Gallery' )
								);
		
		$ui->pfs->append( 'select', 'link_to', 'user:smugmugsilo__link_to', _t( 'Link to:' ) );
			$ui->pfs->link_to->options = $link_to_array;
			$ui->pfs->link_to->template = 'smugmugsilo_select';
		$ui->pfs->append( 'select', 'link_to_size', 'user:smugmugsilo__link_to_size', _t( 'Link to Size:' ) );
			unset($imgSizes['Custom']);	// Temporarily remove the "Custom" Option as we don't use it yet
			$ui->pfs->link_to_size->options = $imgSizes;
			$ui->pfs->link_to_size->template = 'smugmugsilo_select';
		if ($user->info->smugmugsilo__link_to != 'image') {
			$ui->pfs->link_to_size->class = 'formcontrol hidden';
		}
		$ui->pfs->append( 'text', 'link_to_custom_size', 'user:smugmugsilo__link_to_custom_size', _t( 'Custom Size of Longest Edge (px):' ), 'smugmugsilo_text' );
		if ( $ui->pfs->smugmugsilo__link_to_size != 'Custom' ) {
			$ui->pfs->link_to_custom_size->class = 'formcontrol hidden';
		}
		
		/* Add Video config support */
		$ui->append( 'fieldset', 'vfs', _t( 'Video Options: ' ) );
		$ui->vfs->append( 'text', 'vid_max_width', 'user:smugmugsilo__vid_max_width', _t( 'Max Video Width (px):' ), 'smugmugsilo_text' );
		$ui->vfs->append( 'radio', 'show_logo', 'user:smugmugsilo__show_logo', _t( 'Show Logo? ' ), array( '' => 'Yes', 'nologo' => 'No' ), 'smugmugsilo_radio' );
		$ui->vfs->append( 'radio', 'share_btn', 'user:smugmugsilo__share_btn', _t( 'Show Share button? ' ), array( '' => 'Yes', 'noshare' => 'No' ), 'smugmugsilo_radio' );
		$ui->vfs->append( 'radio', 'home_btn', 'user:smugmugsilo__home_btn', _t( 'Show Home button? ' ), array( '' => 'Yes', 'nohome' => 'No' ), 'smugmugsilo_radio' );
		$ui->vfs->append( 'radio', 'hd_btn', 'user:smugmugsilo__hd_btn', _t( 'Show HD button? ' ), array( '' => 'Yes', 'nohd' => 'No' ), 'smugmugsilo_radio' );
		$ui->vfs->append( 'radio', 'stretch_btn', 'user:smugmugsilo__stretch_btn', _t( 'Show Stretch button? ' ), array( '' => 'Yes', 'sb' => 'No' ), 'smugmugsilo_radio' );
		$ui->vfs->append( 'radio', 'stretch', 'user:smugmugsilo__stretch', _t( 'Stretch? ' ), array( '' => 'Yes', 'fs' => 'No' ), 'smugmugsilo_radio' );



		$ui->append( 'submit', 'save', _t( 'Save' ) );
		$ui->on_success( array( $this, 'save_config_msg' ) );
		$ui->out();
		unset($this->smug);
    }

	public static function save_config_msg( $ui )
	{
		$ui->save();
		Session::notice( _t( 'Options saved' ) );
		return false;
	}

	/**
 	 * Add custom styling to admin interface
	 */
	public function action_admin_header( $theme )
	{
		if ( Controller::get_var( 'configure' ) == $this->plugin_id || Controller::get_var( 'page' ) == 'publish' ) {
			Stack::add( 'admin_stylesheet', array( URL::get_from_filesystem( __FILE__ ) . '/lib/css/admin.css', 'screen'), 'smugmug_silo_admin_css' );
		}
	}

    /**
     * Add custom Javascript controls to the footer of the admin interface
     **/
    public function action_admin_footer( $theme )
	{
	    if( Controller::get_var( 'page' ) == 'publish' ) {
			$user = User::identify();
			$size = $user->info->smugmugsilo__image_size;
			$sizeURL = array( 'Ti' => 'TinyURL', 'Th' => 'ThumbnailURL', 'S' => 'SmallURL', 'M' => 'MediumURL', 'L' => 'LargeURL', 'XL' => 'XLargeURL', 'X2' => 'X2LargeURL', 'X3' => 'X3LargeURL', 'O' => 'OriginalURL', 'Custom' => 'Custom' );

		    if ( $size == "Custom" ) {
				$customSize = $user->info->smugmugsilo__custom_size;
			    $size = "{$customSize}x{$customSize}";
		    }
			
		    $nickName = $user->info->smugmugsilo__nickName;
			$lockicon = URL::get_from_filesystem( __FILE__ ) . '/lib/imgs/lock.png';
			
			$vOpts = array( $user->info->smugmugsilo__show_logo, $user->info->smugmugsilo__share_btn, $user->info->smugmugsilo__home_btn, $user->info->smugmugsilo__hd_btn, $user->info->smugmugsilo__stretch_btn, $user->info->smugmugsilo__stretch );
			$videoOpts = ( count( $vOpts ) > 0 ) ? '&' . implode( '&', $vOpts ) : '';
			$vidWidth = ( $user->info->smugmugsilo__vid_max_width ) ? $user->info->smugmugsilo__vid_max_width : 640;
			
			

		    echo <<< SMUGMUG_ENTRY_CSS_1
		    <script type="text/javascript">
			    /* This is a bit of a fudge to over-write the dblclick functionality.
			     * We introduce our own dblclick which inserts the default image size as defined by the user.
				 *
			     * I use mouseover here because media.js, which sets the initial dblclick, is reloaded each time
			     * the user clicks on a "dir" entry, but this code isn't.
				 */
			    $('#silo_smugmug .mediaphotos').bind('mouseover', function() {
						// We only want to change the default behaviour for phohts, not videos
						var photos = $('#silo_smugmug .media').filter(function() {
							return $(this).children('ul.smugmugvid').length == 0;
						});
					    photos.unbind('dblclick');
					    photos.dblclick(function(){
							var id = $('.foroutput', this).html();
							insert_smugmug_photo(id, habari.media.assets[id], "Default", "{$size}");
							return false;
					    });
			    });

				// We Only show 5 buttons as I doubt anyone will be inserting larger images into posts.
			    habari.media.output.smugmugimg = {
				    Ti: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.TinyURL, 'Ti');},
				    Th: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.ThumbURL, 'Th');},
				    S: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.SmallURL, 'S');},
				    M: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.MediumURL, 'M');},
				    L: function(fileindex, fileobj) {insert_smugmug_photo(fileindex, fileobj, fileobj.LargeURL, 'L');}
			    }
					
				function insert_smugmug_photo(fileindex, fileobj, filesizeURL, size) {
					ratio = fileobj.Width/fileobj.Height;

					// Scale images correctly and set appropriate width and height values automatically
					var dimensions = new Array();

					if ( fileobj.SquareThumbs === true ) {
						dimensions['Ti'] = new Array(100, 100);	// width, height
						dimensions['Th'] = new Array(150, 150);
					} else {
						if ( ratio > 1) {
							dimensions['Ti'] = new Array(100, Math.ceil(100/ratio));	// width, height
							dimensions['Th'] = new Array(150, Math.ceil(150/ratio));
						} else {
							dimensions['Ti'] = new Array(Math.ceil(100*ratio), 100);
							dimensions['Th'] = new Array(Math.ceil(150*ratio), 150);
						}
					}

					if ( ratio > 1) {
						dimensions['S'] = new Array(400, Math.ceil(400/ratio));
						dimensions['M'] = new Array(600, Math.ceil(600/ratio));
						dimensions['L'] = new Array(800, Math.ceil(800/ratio));
						dimensions['XL'] = new Array(1024, Math.ceil(1024/ratio));
						dimensions['X2'] = new Array(1280, Math.ceil(1280/ratio));
						dimensions['X3'] = new Array(1600, Math.ceil(1600/ratio));
					} else {
						dimensions['S'] = new Array(Math.ceil(300*ratio), 300);
						dimensions['M'] = new Array(Math.ceil(450*ratio), 450);
						dimensions['L'] = new Array(Math.ceil(600*ratio), 600);
						dimensions['XL'] = new Array(Math.ceil(768*ratio), 768);
						dimensions['X2'] = new Array(Math.ceil(960*ratio), 960);
						dimensions['X3'] = new Array(Math.ceil(1200*ratio), 1200);
					}
					dimensions['O'] = new Array(fileobj.Width, fileobj.Height);

					if (dimensions[(size)] == undefined) {
						split = size.split('x');
						if (ratio > 1) {
							w = split[0];
							h = Math.round(split[0]/ratio);
						} else {
							w = Math.round(split[0]*ratio);
							h = split[0];
						}
						size = (w)+'x'+(h);
						dimensions[(size)] = new Array(w, h);
					}

				    if (filesizeURL == "Default") {
					    filesizeURL = "http://{$nickName}.smugmug.com/photos/"+fileobj.id+"_"+fileobj.Key+"-"+size+"."+fileobj.Format.toLowerCase();
				    }
					

SMUGMUG_ENTRY_CSS_1;
switch ($user->info->smugmugsilo__link_to) {
	case 'nothing':
		echo "habari.editor.insertSelection('<img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" title=\"'+ fileobj.Caption + '\" width=\"' + dimensions[(size)][0] + '\" height=\"'+dimensions[(size)][1]+'\" />');";
	break;
	case 'image':	// TODO: Get this working with custom sizes
		$linkTo = $sizeURL[$user->info->smugmugsilo__link_to_size];
		echo "habari.editor.insertSelection('<a href=\"' + fileobj.{$linkTo} + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" title=\"'+ fileobj.Caption + '\" width=\"' + dimensions[(size)][0] + '\" height=\"'+dimensions[(size)][1]+'\" \/><\/a>');";
	break;
	case 'smugmug':
		echo "habari.editor.insertSelection('<a href=\"' + fileobj.AlbumURL + '\"><img src=\"' + filesizeURL + '\" alt=\"' + fileobj.id + '\" title=\"'+ fileobj.Caption + '\" width=\"' + dimensions[(size)][0] + '\" height=\"'+dimensions[(size)][1]+'\" \/><\/a>');";
	break;
}

echo <<< SMUGMUG_ENTRY_CSS_2

			    }

				habari.media.output.smugmugvid = {
					Embed_Video: function(fileindex, fileobj) {insert_smugmug_video(fileindex, fileobj);}
				}

				function insert_smugmug_video(fileindex, fileobj) {
					var ratio = fileobj.Width/fileobj.Height;
					if ( fileobj.Width > 400 ) {
						var vidWidth = {$vidWidth};
						var vidHeight = Math.ceil(vidWidth/ratio);
					} else {
						var vidWidth = fileobj.Width;
						var vidHeight = fileobj.Height;
					}
					habari.editor.insertSelection('<iframe frameborder="0" scrolling="no" width="'+vidWidth+'" height="'+vidHeight+'" src="http://api.smugmug.com/services/embed/'+fileobj.id+'_'+fileobj.Key+'?width='+vidWidth+'&height='+vidHeight+'{$videoOpts}"></iframe>');
				}
				
			    habari.media.preview.smugmugimg = function(fileindex, fileobj) {
				    out = '<div class="mediatitle" title="'+ fileobj.Caption +'">';
				    if (fileobj.Hidden == 1) {
					    out += '<span class="hidden_img"><\/span>';	/* This is a bit of a nasty fudge, but it gets the job done. */
				    }
				    out += '<a href="' + fileobj.AlbumURL + '" class="medialink" target="_blank" title="Go to gallery page on SmugMug">media<\/a>' + fileobj.TruncTitle + '<\/div><div class="mediathumb"><img src="' + fileobj.ThumbURL + '" \/></div>';
				    return out;
			    }
				
				habari.media.preview.smugmugvid = function(fileindex, fileobj) {
				    out = '<div class="mediatitle" title="'+ fileobj.Caption +'">';
				    if (fileobj.Hidden == 1) {
					    out += '<span class="hidden_img"><\/span>';	/* This is a bit of a nasty fudge, but it gets the job done. */
				    }
				    out += '<a href="' + fileobj.AlbumURL + '" class="medialink" target="_blank" title="Go to gallery page on SmugMug">media<\/a>' + fileobj.TruncTitle + '<\/div><div class="mediathumb"><img src="' + fileobj.ThumbURL + '" \/></div><div class="playBtn"></div>';
				    return out;
			    }
		    </script>
SMUGMUG_ENTRY_CSS_2;
	    }
	    // Javascript required for config panel
	    if ( Controller::get_var( 'configure' ) == $this->plugin_id ) {
		    // Authorize specific Javascript
		    if ( Controller::get_var( 'configaction' ) == 'authorise' ) {
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
		    if ( Controller::get_var( 'configaction' ) == 'configure' ) {
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

				    if ($("#link_to select :selected").val() == 'image') {
					    $("#link_to_size").removeClass("hidden");
						    } else {
					    $("#link_to_size").addClass("hidden");

					    }
				    $("#link_to select").change(function () {
					    if (this.value == "image") {
						    $("#link_to_size").removeClass("hidden");
					    } else {
						    $("#link_to_size").addClass("hidden");
					    }
				    });

					if ($("#link_to_size select :selected").val() == 'Custom') {
					    $("#link_to_custom_size").removeClass("hidden");
						    } else {
					    $("#link_to_custom_size").addClass("hidden");

					    }
				    $("#link_to_size select").change(function () {
					    if (this.value == "Custom") {
						    $("#link_to_custom_size").removeClass("hidden");
					    } else {
						    $("#link_to_custom_size").addClass("hidden");
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

    /**
     * Clear cache files when de-activating
	 *
	 * @return void
     **/
    public function action_plugin_deactivation( $file )
    {
      if ( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
        /* Uncomment to delete options on de-activation *
        $user = User::identify();
        unset( $user->info->smugmugsilo__token );
        unset( $user->info->smugmugsilo__custom_size );
        unset( $user->info->smugmugsilo__image_size );
        unset( $user->info->smugmugsilo__nickName );
		unset( $user->info->smugmugsilo__link_to_size );
		unset( $user->info->smugmugsilo__link_to );
        $user->info->commit();
        /* */
		  // This will fail if we can't access SmugMug for some reason.
			$this->clearCaches();
			rmdir( $this->smug->cache_dir );
      }
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
			$controls[] = $this->link_panel( self::SILO_NAME . '/' . $path, 'clearCache', _t( 'ClearCache' ) );
		    if( User::identify()->can( 'upload_smugmug' ) ) {
			    if ( strchr( $path, '/' ) ) {
				    $controls[] = $this->link_panel( self::SILO_NAME . '/' . $path, 'upload', _t( 'Upload' ) );
			    }
		    }
			$controls['status'] = $this->status;
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
	    }
		return $panel;
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
		$phpSmug_ok = $this->is_auth();
		if( $phpSmug_ok ){
			$actions['configure'] = _t( 'Configure' );
			$actions['deauthorise'] = _t( 'De-Authorize' );
		}
		else {
			$actions['authorise'] = _t( 'Authorize' );
		}
	    return $actions;
    }

    /**
     * Return a link for the panel at the top of the silo window.
	 *
	 * @return string
     */
    public function link_panel( $path, $panel, $title )
    {
	    return '<a href="#" onclick="habari.media.showpanel(\''.$path.'\', \''.$panel.'\');return false;">' . $title . '</a>';
    }


	/**************************** SILO METHODS *********************************/


    /**
    * Return basic information about this silo
    *   name- The name of the silo, used as the root directory for media in this silo
    *   icon- An icon to represent the silo
	 *
	 * @return array
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
		$img_extras = 'FileName,Hidden,Caption,Format,TinyURL,SmallURL,ThumbURL,MediumURL,LargeURL,XLargeURL,X2LargeURL,X3LargeURL,OriginalURL,Width,Height,Video320URL,Video640URL,Video960URL,Video1280URL,Video1920URL'; // Grab only the options we need to keep the response small
		$results = array();
		$section = strtok( $path, '/' );

		switch( $section ) {
			case 'recentPhotos':
				$cache_name = ( array( 'smugmugsilo', "recentphotos".$user ) );
				if ( Cache::has( $cache_name ) && !Cache::expired( $cache_name ) ) {
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
						$url_parts = parse_url( $photoURL );
						// replace the ! at the beginning of the fragment
						$fragment = str_replace('!', '', $url_parts['fragment']);
						parse_str($fragment);
						$info = $this->smug->images_getInfo( "ImageID={$i}",
															 "ImageKey={$k}",
															 "Extras={$img_extras}" );
						$this->status = $this->smug->mode;
						$props = array( 'TruncTitle' => $i, 'FileName' => '&nbsp;', 'Hidden' => 0 );
						foreach( $info as $name => $value ) {
							if ($name == 'Caption') {
								if ($value != '') {
									$props['Caption'] = MultiByte::convert_encoding( strip_tags( $value ) );
									$props['TruncTitle'] = Utils::truncate( $props['Caption'], 23, FALSE );
								}
								else {
										$props['TruncTitle'] = Utils::truncate( $props['FileName'], 23, FALSE );
										$props['Caption'] = MultiByte::convert_encoding( $props['FileName'] );
								}
							}
							else if ($name == 'Album') {
								$props['AlbumURL'] = $value['URL'];
							}
							else {
								$props[$name] = (string) $value;
							}

							unset( $props['FileName'] );
							$props['filetype'] = ( isset( $props['Video320URL'] ) || isset( $props['Video640URL'] ) || isset( $props['Video960URL'] ) || isset( $props['Video1280URL'] ) || isset( $props['Video1920URL'] ) ) ? 'smugmugvid' : 'smugmugimg';
							$results[] = new MediaAsset(
												self::SILO_NAME . '/recentPhotos/' . $i,
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
					if ( Cache::has( $cache_name ) && !Cache::expired( $cache_name ) ) {
						$results = Cache::get( $cache_name );
					}
					else {
						$props = array( 'TruncTitle' => '&nbsp;', 'FileName' => '', 'Hidden' => 0 );
						// TODO: Switch to NiceName URL structure
						$photos = $this->smug->images_get( "AlbumID={$galmeta[0]}",
														   "AlbumKey={$galmeta[1]}",
														   "Extras={$img_extras}" );
						$this->status = $this->smug->mode;
						foreach( $photos['Images'] as $photo ) {
							foreach( $photo as $name => $value ) {
								$props[$name] = (string) $value;
								$props['filetype'] = ( isset( $props['Video320URL'] ) || isset( $props['Video640URL'] ) || isset( $props['Video960URL'] ) || isset( $props['Video1280URL'] ) || isset( $props['Video1920URL'] ) ) ? 'smugmugvid' : 'smugmugimg';
								$props['AlbumURL'] = 'http://'.$user.'.smugmug.com/gallery/'.$galmeta[0].'_'.$galmeta[1].'#'.$photo['id'].'_'.$photo['Key'];
							}
							if ($props['Caption'] != '') {
								$props['Caption'] = MultiByte::convert_encoding( strip_tags( $props['Caption'] ) );
								$props['TruncTitle'] = Utils::truncate( $props['Caption'], 23, FALSE );
							}
							else {
								$props['TruncTitle'] = Utils::truncate( $props['FileName'], 23, FALSE );
								$props['Caption'] = MultiByte::convert_encoding( $props['FileName'] );
							}
							unset( $props['FileName'] );
							$results[] = new MediaAsset(
												self::SILO_NAME . '/photos/' . $photo['id'],
												false,
												$props
												);
						}
						Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
					}
				}
				else {
					$cache_name = ( array( 'smugmugsilo', "recentgalleries".$user ) );
					if ( Cache::has( $cache_name ) && !Cache::expired( $cache_name ) ) {
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
							$this->status = $this->smug->mode;
							$j++;
						}
						foreach( $galleries as $gallery ) {
							$results[] = new MediaAsset(
												self::SILO_NAME . '/recentGalleries/' . (string) $gallery['id'].'_'.$gallery['Key'],
												true,
												array( 'title' => (string) $gallery['Title'] )
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
					if ( Cache::has( $cache_name ) && !Cache::expired( $cache_name ) ) {
						$results = Cache::get( $cache_name );
					}
					else {
						$props = array( 'TruncTitle' => '&nbsp;', 'FileName' => '', 'Hidden' => 0 );
						$galInfo = $this->smug->albums_getInfo( "AlbumID={$galmeta[0]}",
																"AlbumKey={$galmeta[1]}" );
						$squareThumbs = ( array_key_exists('SquareThumbs', $galInfo ) ) ? $galInfo['SquareThumbs'] : FALSE;
						$photos = $this->smug->images_get( "AlbumID={$galmeta[0]}",
														   "AlbumKey={$galmeta[1]}",
														   "Extras={$img_extras}" );
						$this->status = $this->smug->mode;
						foreach( $photos['Images'] as $photo ) {
							foreach( $photo as $name => $value ) {
								$props[$name] = (string) $value;
								$props['filetype'] = ( isset( $props['Video320URL'] ) || isset( $props['Video640URL'] ) || isset( $props['Video960URL'] ) || isset( $props['Video1280URL'] ) || isset( $props['Video1920URL'] ) ) ? 'smugmugvid' : 'smugmugimg';
								$props['AlbumURL'] = 'http://'.$user.'.smugmug.com/gallery/'.$galmeta[0].'_'.$galmeta[1].'#'.$photo['id'].'_'.$photo['Key'];
							}
							if ($props['Caption'] != '') {
								$props['Caption'] = MultiByte::convert_encoding( strip_tags( $props['Caption'] ) );
								$props['TruncTitle'] = Utils::truncate( $props['Caption'], 23, FALSE );
							}
							else {
								$props['TruncTitle'] = Utils::truncate( $props['FileName'], 23, FALSE );
								$props['Caption'] = MultiByte::convert_encoding( $props['FileName'] );
							}
							$props['NiceName'] = $galInfo['NiceName'];
							$props['SquareThumbs'] = ( array_key_exists('SquareThumbs', $galInfo ) ) ? $galInfo['SquareThumbs'] : false;
							unset( $props['FileName'] );
							$results[] = new MediaAsset( 
								self::SILO_NAME . '/photos/' . $photo['id'],
								false,
								$props
								);
						}
						Cache::set( $cache_name, $results, self::CACHE_EXPIRY );
					}
				}
				else {
					// Don't need to cache this as it's quick anyway.
					// Set NickName as it ensure we still work in read-only mode.
					try {
						$galleries = $this->smug->albums_get( "NickName=".User::identify()->info->smugmugsilo__nickName, "Extras=Public" );
						$this->status = $this->smug->mode;
					}
					catch (Exception $e) {
						$this->status = 'ERROR: '.$e->getMessage();
						Session::error($e->getMessage());
						return false;
					}

					foreach( $galleries as $gallery ) {
						$results[] = new MediaAsset(
											self::SILO_NAME . '/galleries/' . (string) $gallery['id'].'_'.$gallery['Key'],
											true,
											// If the gallery is NOT public, mark it by preceding with a lock icon
											// This is a bit of the fudge as the MediaAsset takes an icon argument, but doesn't actually do anything with it.  This would be a great place for it to use it in this case. It would be great if it did.
											array( 'title' => ( ( $gallery['Public'] == TRUE ) ? '' : '<img src="'.URL::get_from_filesystem( __FILE__ ) . '/lib/imgs/lock.png" style="vertical-align: middle; height:12px; width:12px" title="Private Gallery" /> ' ). $gallery['Title'] )
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


	/************************* HELPER METHODS *********************************/

	/**
	 * Simple function to call once to clear multiple cache locations
	 *
	 * @return void
	 */
	private function clearCaches( )
	{
		$this->phpSmugInit();
		$this->smug->clearCache();
		foreach ( Cache::get_group( 'smugmugsilo' ) as $name => $data ) {
			Cache::expire( array( 'smugmugsilo', $name ) );
		}
	}

    /**
     * Check if the application has been authorised to access SmugMug
	 *
	 * @return boolean
     **/
    private function is_auth()
    {
	    static $phpSmug_ok = NULL;
	    if( isset( $phpSmug_ok ) ){
		    return $phpSmug_ok;
	    }

	    $phpSmug_ok = FALSE;
		$token = User::identify()->info->smugmugsilo__token;

		if( $token != '' ){
			
			try {
				$this->phpSmugInit();

				$this->smug->setToken( "id={$token['Token']['id']}",
								   "Secret={$token['Token']['Secret']}" );
				$result = $this->smug->auth_checkAccessToken();
				if( isset( $result ) ){
					$phpSmug_ok = TRUE;
				}
			}
			catch ( Exception $e ) {
				// TODO: Need to cater for more than
				if ( $e->getCode() == 64 || $e->getCode() == 500 ) {
					Session::error( 'SmugMug Media Silo: ' . _t( 'Unable to communicate with SmugMug.' ), 'SmugMug API' );
					// We can't communicate with SmugMug, but we have a token, so lets assume it's valid for the moment. If it's not we'll soon find out.
					$phpSmug_ok = TRUE;
				}
				else if ( $e->getCode() == 98 ) {
					Session::error( 'SmugGal Media Silo: ' . _t( 'SmugMug are having problems so the API has been temporarily disabled. Please try again later.' ), 'SmugMug API' );
				}
				else {
					Session::error( $e->getMessage() . _t( ' Problems detected with your SmugMug Authorization. Please re-authorize.' ), 'SmugMug API' );
					User::identify()->info->smugmugsilo__token = '';
					User::identify()->info->commit();
					unset( $_SESSION['smugmug_token'] );
				}
			}
	    }
	    return $phpSmug_ok;
    }

    /**
     * Function to truncate strings to a set number of characters (23 by default)
     * This is used to truncate the "Title" when displaying the images in the silo
     **/
    private static function truncate( $string, $max = 20, $replacement = '...' )
    {
	    if ( strlen( $string ) <= $max ) {
		    return $string;
	    }
	    $leave = $max - MultiByte::strlen( html_entity_decode( $replacement ) );
	    //return substr_replace( $string, $replacement, $leave );
      return MultiByte::substr( $string, 0, $max ).$replacement;

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
	    if ( Cache::has( $this->cache_name ) && !Cache::expired( $this->cache_name ) ) {
		    $response = Cache::get( $this->cache_name );
	    }
		else {
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
		    Session::error( _t( 'Currently unable to connect to SmugMug to grab feed.' ), 'SmugMug API' );
		    return false;
	    }
    }

	public function phpSmugInit() {
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
		
		// If this instance of Habari has a proxy, configured, configure phpSmug to use it too
		$default = new stdClass();
		$proxy = Config::get( 'proxy', $default );
		if ( isset( $proxy->server ) ) {
			$this->smug->setProxy( "server={$proxy->server}", "port={$proxy->port}", "username={$proxy->username}" , "password={$proxy->password}" );
		}

		// Call a method we know will succeed, so we can get the mode set
		//$this->smug->reflection_getMethods();
	}
}

?>
