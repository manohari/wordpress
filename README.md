wordpress
=========

wordpress plugin

This plugin requires wordpress 4.0 and later versions

CrossPost plugin for all blogs 

== Description ==

This Plugin imports any blog page content inot your wordpress post. It takes URL as input and retreive total content.In version 1 we imported the blog page as it is i.e., it import the header and footer of your blog

== Installation ==

1. Upload the import_blog folder to your wp-content/plugins folder
2. Activate the plugin
3. Click on posts link. You will find CrossPost as one type of post type
4. Click on Crospost link.

= How to use it? =

1. Input the URL from which blog you want to corsspost.
2. If you want to import entire data leave the div ids textbox empty.
3. If you want to pick specified content from the page, give respective div ids as input to the text.
4. If you wish to pick content from multiple divs specify the ids with comma seperator.
5. Click on publish button to import the content into your wordpress blog as post.


zip.vbs and compressFile.sh are script files which takes the folder as input and zip the files

Usage of zib.vbs
Download the file from here into local machine.
go the path that locates the file through command prompt
eg: C:\Users> CScript "C:\foldername or filename to zip" "C:\newzipfilename.zip"
You can specify your own paths and directories

Usage of compressFile.sh
Download the file from here into local machine.
go the path that locates the file through command prompt
eg: ./compressFile.sh "folder or file absolutepath" "newzipfilename.tar"
