Plugin: SmugMug Media Silo
URL: http://www.lildude.co.uk/projects/smugmug-media-silo-plugin/
Plugin Author: Colin Seymour - http://colinseymour.co.uk
Licenses:  SmugMug Media Silo (smugmugsilo.plugin.php) : Apache Software License 2.0
           phpSmug (lib/phpSmug): GNU Public License v3 & Others - See NOTICE

The SmugMug Media Silo plugin implements a Habari silo to access your SmugMug photos
making it easy to include images into posts and pages and also upload images
directly to SmugMug.

Not a SmugMug user yet?  Get $5 off your first year using this reference code: 2ZxFXMC19qOxU

FUNCTIONALITY
-------------

    * Access all your SmugMug images from within the Habari admin interface.
    * Insert an image directly into a post from the Habari admin interface.
    * Configure a default image size for inserted images, with the option of selecting
      a different size at the time of posting too.
    * Configure a custom image size that differs from the defaults offered by SmugMug.
    * Configure what you'd like your image to link to, if anything at all.
    * "Unlisted" galleries and "Hidden" images are clearly marked with a lock icon
      so you don't accidentally insert an image you really didn't want the world to see.
    * Upload an image directly to a specific gallery from within the Habari admin interface
    * Quickly and easily view recently modified albums and uploaded images
    * SmugMug Media Silo caches information (not images) for quicker access and load times
    * Authorization is directly with SmugMug. SmugMug Media Silo uses OAuth to obtain
      access to your images, so does NOT require your username or password.


KNOWN LIMITATIONS
-----------------

SmugMug Media Silo is NOT compatible with Habari 0.5.2 and earlier. This is because
several bugs were found in the development of the plugin that have only been resolved
in 0.6 and later.

SmugMug's video functionality has not been tested nor implemented yet, but it will be
coming in a future release.


INSTALLATION
------------

   1. Download either the zip or tar.bz2 to your server
   2. Extract the contents to a temporary location (not strictly necessary, but just being safe)
   3. Move the smugmugsilo directory to /<path to habari>/user/plugins/
   4. Refresh your plugins page and activate the plugin.
   5. Authorize the plugin with SmugMug by clicking "Authorize". This will open
      the "Authorization" options.
   6. Next click "Authorize". This will open the SmugMug authorization page in a
      new window or tab. You may need to login into your SmugMug account.
   7. Accept the access requirements detailed on that page and close the window/tab
      and come back to the Habari Admin page/tab.
   8. Click "Confirm" to confirm you have authorized SmugMug Media Silo to access your SmugMug account.
   9. Configure the silo to suit your needs - at the moment, it's just a matter of
      selecting the default image size and what you would like inserted images to
      link to (Nothing, a larger image, the image's gallery page on SmugMug)

That's it. You're ready to use the SmugMug Media Silo.


UPGRADE
-------

The upgrade procedure is as per the installation procedure, but please ensure you
de-activate the plugin first.  This will ensure your current settings are merged
with any new options that may be added with later releases.


USAGE
-----

Usage is incredibly simple: simply click on the "SmugMug" silo button within the
entry/page creation/management page within Habari and select the gallery and image
you want to insert into your post/page.

Double-clicking the thumbnail will insert the default image size as configured in
the SmugMug Media Silo configuration options wrapped in a link to the destination
of your choice.

Alternatively, click one of the Ti, Th, S, M or L buttons under the thumbnail to insert a
Tiny, Thumbnail, Small, Medium or Large image respectively. These images will use the
dimensions as provided by SmugMug.


ADDITIONAL INFORMATION
----------------------

The ordering of images and galleries is as they are configured on SmugMug. SmugMug Media Silo
does NOT change the order. If you wish to change the order or any other settings specific to
your images, you will need to do so from within SmugMug's own administration controls.

Each image in the silo has a title associated with it. This will either be up to the first
23 characters of the first line of the caption (you can hover over the title to
view the full caption) or the image's filename.


REVISION HISTORY
----------------

1.0		- Initial release.
