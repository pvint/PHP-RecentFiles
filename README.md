# PHP-RecentFiles
PHP Class to get file listings sorted by date modified

  Use this class to get a listing of files sorted by creation date

       Basic usage:
       Create an instance:     $myFiles = new RecentFiles();
       Set directory or directories to search by either:
                               $myFiles->directory = 'documents/';     // note that the path can be
                                                                       // relative from webroot or absolute
                               OR
                               $myFiles->directories[] = 'documents';  // as an array will search all dirs
                               $myFiles->directories[] = 'oldDocs';
               NOTE: Directories without a leading slash will be treated as relative to the webserver's root dir (ie: /var/www/localhost/htdocs/)
                       
       Note: If BOTH $myFiles->directory AND $myFiles->directories are set, $myFiles->directory will be ignored

       Set the maximum number of files to list:
                               $myFiles->numFiles = 5;
       Get the files:          $myFiles->get();
       $myFiles->files will now contain an associative array of the files with their last modified timestamps as the key and the filename (including path) as the value

       Use the urlEncode() method to encode the filename for links

