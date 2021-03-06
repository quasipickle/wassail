<?PHP
  /*****
   * Class: ImageManager
   * Purpose: Used by the help system, this class handles all things involved with managing the images uploaded & used by said system
   */


class ImageManager
{
  public $error;
  public $errorcode;
  public $filename;
  public $tmp_name;

  function __construct($file_field = 'userfile')
  {
    if(isset($_FILES['userfile']))
    {
      $this->errorcode = $_FILES[$file_field]['error'];
      $this->filename = $_FILES[$file_field]['name'];
      $this->tmp_name = $_FILES[$file_field]['tmp_name'];
    }
  }


  /*****
   * Function: uploadOK()
   * Purpose: To check the error code generated by uploading and generate errors if the code wasn't UPLOAD_ERR_OK
   */
  function uploadOK()
  {
    if($this->errorcode != UPLOAD_ERR_OK)
    {
      switch($this->errorcode)
      {
      case UPLOAD_ERR_INI_SIZE:
	$this->error = 'Image was too large.  It exceeded the maximum filesize allowed by PHP.  Contact TLS & get them to increase the <em>upload_max_filesize</em> setting in php.ini .';
	return FALSE;
	break;
      case UPLOAD_ERR_FORM_SIZE:
	$this->error = 'Image was too large. It exceeded the maximum filesize allowed by the form.  Contact TLS & get them to increase the <em>MAX_FILE_SIZE</em> in /srv/www/htdocs/include/templates/help.imagemanager.tpl .';
	return FALSE;
	break;
      case UPLOAD_ERR_PARTIAL:
	$this->error = 'Image upload did not complete.  Please try again.';
	return FALSE;
	break;
      case UPLOAD_ERR_NO_FILE:
	$this->error = 'No file specified.';
	return FALSE;
	break;
      case UPLOAD_ERR_NO_TMP_DIR:
	$this->error = 'No temporary directory available.  Contact TLS.';
	return FALSE;
	break;
      case UPLOAD_ERR_CANT_WRITE:
	$this->error = 'Failed to write to disk.  Contact TLS.';
	return FALSE;
	break;
      case UPLOAD_ERR_EXTENSION:
	$this->error = 'Improper file extension.';
	return FALSE;
	break;
      default:
	$this->error = 'Unknown error value: '.$this->errorcode;
	return FALSE;
	break;
      }
    }
    else
      return TRUE;
  }

  /*****
   * Function: fileOK()
   * Purpose: To make sure the file specified by $this->tmp_name is 100% kosher.
   *          It checks to make sure the file is an uploaded file, is an image, is a valid image
   *          and doesn't already exist.
   */
  function fileOK()
  {
    if(!is_uploaded_file($this->tmp_name))
    {    
      $this->error = 'File is not considered an uploaded file';
      return FALSE;
    }

    $image_properties = @getimagesize($this->tmp_name);
    if(!$image_properties)
    {
      $this->error = 'File is not an image';
      return FALSE;
    }
    
    if(!in_array($image_properties[2],array(IMAGETYPE_JPEG,IMAGETYPE_GIF,IMAGETYPE_PNG)))
    {
      $this->error = 'File is not a JPG, GIF, or PNG';
      return FALSE;
    }

    if(file_exists(HELP_IMAGE_DIR.$this->filename))
    {
      $this->error = 'File already exists';
      return FALSE;
    }

    return TRUE;
  }
  

  /*****
   * Function: moveFile()
   * Purpose: To move the file specified by $this->tmp_name to the special help image directory
   */
  function moveFile()
  {
    if(@move_uploaded_file($this->tmp_name,HELP_IMAGE_DIR.$this->filename))
      return TRUE;
    else
    {
      $this->error = 'Unable to move image from temporary directory to image directory';
      return FALSE;
    }
  }

  /*****
   * Function: makeThumb()
   * Purpose: to generate a thumbnail of $this->filename
   */
  function makeThumb()
  {
    /* Retrieve image properties */
    $imageinfo = getimagesize(HELP_IMAGE_DIR.$this->filename,$imageinfo);
           
    /* Get original size */
    $old_x = $imageinfo[0];
    $old_y = $imageinfo[1];

    /* Load image data */
    switch ($imageinfo[2]) 
    {
      case IMAGETYPE_GIF: $image = imagecreatefromgif(HELP_IMAGE_DIR.$this->filename); break;
      case IMAGETYPE_JPEG: $image = imagecreatefromjpeg(HELP_IMAGE_DIR.$this->filename); break;
      case IMAGETYPE_PNG: $image = imagecreatefrompng(HELP_IMAGE_DIR.$this->filename); break;
    }
    
    /* Calculate sizes of thumbnail. */
    if($old_x > MAX_THUMB_WIDTH || $old_y > MAX_THUMB_HEIGHT)
    {
      if(($old_x/MAX_THUMB_WIDTH) > ($old_y/MAX_THUMB_HEIGHT))
      {
	$new_x = MAX_THUMB_WIDTH;
	$new_y = (MAX_THUMB_WIDTH/$old_x)*$old_y;
      }
      else
      {
	$new_y = MAX_THUMB_HEIGHT;
	$new_x = (MAX_THUMB_HEIGHT/$old_y)*$old_x;
      }
    }
    else
    {
      $new_x = $old_x;
      $new_y = $old_y;
    }

    /* create thumbnail image resource */
    $thumbnail = imagecreatetruecolor($new_x,$new_y);
    imagecopyresampled($thumbnail,$image,0,0,0,0,$new_x,$new_y,$old_x,$old_y);

    $thumb_file_path = HELP_IMAGE_DIR.'thumbs/'.$this->filename;

    /* save it out as the same format as it came in */
    switch($imageinfo[2])
    {
      case IMAGETYPE_GIF:
	imagetruecolortopalette($thumbnail,0,256);
	imagegif($thumbnail,$thumb_file_path);
	break;
      case IMAGETYPE_JPEG:
	imagejpeg($thumbnail,$thumb_file_path,80);
	break;
      case IMAGETYPE_PNG:
	imagepng($thumbnail,$thumb_file_path);
	break;
    }
    return TRUE;
  }
  

  /*****
   * Function: delete()
   * Purpose: To delete an image
   * Note: This function runs $filename though realpath() to ensure it's not a hack attempt
   */
  function delete($filename)
  {
    /* realpath resolves any ../../.., etc tricks that might be going on */
    $file_to_delete = realpath(HELP_IMAGE_DIR.$filename);
    $thumb_to_delete = realpath(HELP_IMAGE_DIR.'thumbs/'.$filename);

    
    /* Make sure the file is actually in the proper directories */
    if(dirname($file_to_delete).'/' == HELP_IMAGE_DIR &&
       dirname($thumb_to_delete) == HELP_IMAGE_DIR.'thumbs')
    {
      if(@unlink($file_to_delete))
	if(@unlink($thumb_to_delete))
	  return TRUE;
	else
	{	
	  $this->error = 'Unable to delete thumbnail';
	  return FALSE;
	}
      else
      {
	$this->error = 'Unable to delete image';
	return FALSE;
      }
    }
    else
    {
      $this->error = 'File was not in the appropriate image directory';
      return FALSE;
    }
  }


  /*****
   * Function: getList()
   * Purpose: To retrieve a list of all the images & some properties of those imags
   *          for use in the template
   */

  function getList()
  {
    chdir(HELP_IMAGE_DIR);
    $images = glob('{*.jpg,*.JPG,*.jpeg,*.JPEG,*.gif,*.GIF,*.PNG,*.png}',GLOB_BRACE);

    if(is_array($images))
       if(count($images) > 0)
       {
	 sort($images);
	 foreach($images as $index=>$filename)
	 {
	   $stats = stat(HELP_IMAGE_DIR.$filename);
	   $stat_images[$index]['filename'] = $filename;
	   $stat_images[$index]['size'] = number_format($stats['size']/1000,2);
	   $stat_images[$index]['last_modified'] = $stats['mtime'];
	 }
	 return $stat_images;
       }
       else
	 return array();
    else
      return FALSE;
	 
  }
}
?>