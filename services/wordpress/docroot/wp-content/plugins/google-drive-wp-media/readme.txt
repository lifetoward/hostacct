=== Google Drive WP Media ===
Contributors: moch-a
Donate link: http://www.mochamir.com/
Tags: google drive, google drive upload, media library, google drive plugin, gallery, featured image, download, files hosting, image, media, pictures, links, images, post, upload, hosting storage, google, shortcode, image galleries, album
Requires at least: 3.5
Tested up to: 4.1
Stable tag: 2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress Google Drive integration plugin. Google Drive on Wordpress Media Publishing. Upload files to Google Drive from WordPress blog.

== Description ==

Google Drive on Wordpress Media Publishing. Direct access to your Google Drive, allows you to manage your files remotely from your WordPress blog.
Upload and share your files directly from your WordPress blog to Google Drive.

New: Auto create thumbnails and Chunking Option available, just navigate to the Options page and customize your settings to help suit your needs.

Features:

* Shortcode button, insert shortcode to embed Google Drive file directly from your post editor.
* Option to auto insert your Google Drive files into your WordPress Media Library.
* Attach your Google Drive files to your posts.
* Upload your files from your WordPress Administration to Google Drive.
* Create folder to storing your files.
* Enable to attach any files to posts.
* 700kb chunked for handle uploading large files.
* Displaying current storage space.
* Create WordPress image galleries.
* Dummy internal urls.
* Delete Google Drive file and folder
* Media Library categories
* Auto create Media Library category based on Google Drive folder name
* Filtering files by its categories in Add Media upload tab

Shortcode for Single File Preview: **[gdwpm id="GOOGLE-DRIVE-FILE-ID"]**

Required:

* PHP 5.3.0
* cURL

== Installation ==

1. Extract google-drive-wp-media into your WordPress plugins directory (wp-content/plugins).
1. Log in to WordPress Admin. Go to the Plugins page and click Activate for Google Drive WP Media
1. On Admin backend, go to Media >> Google Drive WP Media
1. Follow the instructions.

== Frequently Asked Questions ==

= How to save/insert/move (existing) files via the standard Google Drive interface (web, or local folder on computer) and have them show up in the Google Drive WP Media interface in WordPress? =
1. login to https://drive.google.com/?authuser=0#shared-with-me.
2. select folder that has been created by this plugin, click "Add to My Drive".
3. Now the folder has been included into "My Drive" and you have an access to insert existing files into this folder.

to insert or move existing files

1. click "My Drive", and you will see your existing files/folders including folder from "Shared with Me" that you have been added before.
2. select files (not folder) and then click "More" to show the dropdown menu, click "Move to".
3. select folder from "Shared with Me" that you have been added before, click "Move"

note: your files must be set to public to allows anyone to view or download your files.

= Can you tell me why I can't see the files or folders in my google drive that supposedly are uploading to my drive? =
All uploaded files will listed in "Shared with Me" view https://drive.google.com/?authuser=0#shared-with-me (classic Google Drive) or "Incoming" area https://drive.google.com/drive/#incoming (new Google Drive).

= How to displaying images in thumbnail size in the gallery? =
Click the "Options" tab and enable the "Auto create Thumbnails" option.

= I got a problem and the message is "An error occurred: Unable to parse the p12 file. Is this a .p12 file? Is the password correct? OpenSSL error:". what I missed? =
The error reported that was 'Unable to parse the p12 file', have you write the correct url path of p12 file.? can you access or download it via web browser? in addition if your domain setting use non-www or www only, then you have to add/remove www in url path. If the problem still exists, just upload your p12 file to your Google Drive and change the sharing setting set to public.

= I got a problem and the message is "An error occurred: Error refreshing the OAuth2 token, message: '{ "error" : "invalid_grant" }'". what I missed? =
There's something wrong (typo or something) w/ your Service Account Name setting. Please make sure, there's no white space in the form fields. especially Service Account Name field or create New Client ID.

= How to insert zip file into post as a Google Drive link, not linked to attachment page? =
Click file which you want to attach, on ATTACHMENT DISPLAY SETTINGS, change Link to Media File. Click Insert into post.

== Changelog ==

= 2.4 =

* Bugs fixed
* Image Galleries added

= 2.3 =

* Bugs fixed

= 2.2.9 =

* Duplicate variable name fixed

= 2.2.8 =

* Undefined variable fixed
* Image sizes error fixed
* Restrict plugin access only for users who have the "activate_plugins" capability

= 2.2.7 =

* Pagination for empty page fixed

= 2.2.6 =

* Default values fixed

= 2.2.5 =

* Option Auto Create Thumbnails added
* Image Url changed
* Google Drive upload type changed to Resumable Upload protocol
* Split request into items and pages
* Chunking settings added

= 2.2.4 =

* Added repair tool for hidden folder
* Added JQuery UI selectmenu for WordPress 4.1
* Clear list fuction added
* Nonce Updated

= 2.2.3 =

* Detail Account Information updated
* New: Auto generate shortcode for embedding video
* Duplicated function removed
* Sanitize description input text

= 2.2.2 =

* New feature added: Show the thumbnail on mouseover
* Fixed some codes 

= 2.2.1 =

* Minor update

= 2.2 =

* Items/page initial request option added 
* Pagination on page request
* Request/displaying Google Drive fileslist separated by items/page
* New animation

= 2.1 =

* Shortcode button added
* Predefined shortcode option added
* Bugs/typo fixed
* jQuery UI updated to the latest version (v1.11.2)

= 2.0 =

* Pagination option view added
* Shared coloumn replaced with File size
* Updated Shortcode with Specific width & height
* Donation button added

= 1.9 =

* File & Folder list pagination added

= 1.8 =

* Alternative openssl sign function (as 2nd option) added

= 1.7 =

* Added shortcode for single file preview

= 1.6 =

* Enable preview in file list
* Compatible up to Version 3.9

= 1.5 =

* Feature to delete files/folder added
* Media Library categories added

= 1.4 =

* Url Rewrite bug fixed and optimized
* Added CSS files

= 1.3 =

* maxResults parameter added
* WP Enqueue style added
* Added new option to rewrite urls

= 1.2 =

* Image size bug fixed

= 1.1 =

* Bug fixed on default storage option
* Option to 'Add to Media Library' on default storage option added

= 1.0 =

* Plugin images location moved/changed to local plugin directory
* Added icon tabs

= 0.9 =

* Added Sanitizing for API Key form fields

= 0.8 =

* jQuery UI Themes added
* Added wpnonce
* Custom Google Api config

= 0.7 =

* Google Drive Account Information added
* jQueryui ajax tabs added

= 0.6 =

* Option to reload page after folder creation added
* Typo fixed
* jQuery noConflict() mode compatibility problems fixed

= 0.5 =

* Minimum requirement error handling added
* mkdir function removed

= 0.4 =

* Added 700kb chunked for handle uploading large files activity.
* finfo problem fixed.

= 0.3 =

* Override default media upload dir added
* GUI and CSS changed
* JQuery upload added

= 0.2 =

* Sanitize input text when creating folder
* Error create folder missing argument fixed

= 0.1 =

* Initial release, it's a beta version.

== Upgrade Notice ==

None

== Screenshots ==

1. Google Drive API Key Setup
2. Folder Creation
3. Upload file
4. Files list
5. Media Upload Tab