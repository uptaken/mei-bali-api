<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Storage;
use Auth;
use Hash;
use Curl;
use Image;
use Mail;
use File;
use Carbon\Carbon;

use App\Http\Controllers\Helper\CacheHelper;

class FileHelper{
  private $max_width = 1200;
  private $quality_compress = 100;
  private $image_extension = 'webp';
  private $image_content_type = 'image/webp';

  public function manage_image($image, $model, $filesystem, $column = 'file_name', $file_name = '', $extension = '', $save_original = false){
    if(!empty($image) && !empty($image["file"]) && $image["file"] != ''){
      $image1 = Image::make($image["file"]);
      // dd($image1 instanceof \Intervention\Image\Image);
      // $image1 = $image['file'];
      $req = new Request();
      // if(!empty($image['original_rotation'])){
      //   $req->original_rotation = $image['original_rotation'];
      //   $image1->rotate($image['original_rotation']);
      // }
      $file_name = $this->save_image($image1, $filesystem, $file_name == '' ? $model->id : $file_name, $extension, $save_original);
      if(!empty($model))
        $model->{$column} = $file_name;
    }
  }

  public function manage_file($file, $model, $filesystem, $column = 'file_name', $mime_type_column = ''){
    if(!empty($file) && !empty($file["file"])){
      $file_name_split = explode('.',$file['file_name']);
      $extension = $file_name_split[count($file_name_split) - 1];
      $file_name = $this->save_file($file['file'], $filesystem, $model->id, $extension);
      $model->{$column} = $file_name;
      if($mime_type_column != ""){
        if($extension == 'docx')
          $model->{$mime_type_column} = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        else if($extension == 'pdf')
          $model->{$mime_type_column} = 'application/pdf';
        else if($extension == 'png')
          $model->{$mime_type_column} = 'image/png';
        else if($extension == 'jpeg' || $extension == 'jpg')
          $model->{$mime_type_column} = 'image/jpeg';
      }
    }
  }

  public function save_image($image, $storage, $file_name, $extension = '', $save_original = false){
		$cache_helper = new CacheHelper();

		// dd(Storage::disk($storage)->put($file_name.'.jpg', $image->encode('jpg', 100)));
    if(
			$image instanceof \Intervention\Image\Image &&
			!$save_original && $this->max_width > 0 &&
			$image->width() > $this->max_width
		)
      $image->resize($this->max_width, null, function ($constraint) {
        $constraint->aspectRatio();
      });


    Storage::disk($storage)->put($file_name.'.'.($extension == '' ? $this->image_extension : $extension), $image instanceof \Intervention\Image\Image ? $image->encode($extension == '' ? $this->image_extension : $extension, 100) : new File($image));
		$cache_helper->reset_key([ $storage, $file_name.'.'.($extension == '' ? $this->image_extension : $extension), ]);

    return $file_name.'.'.($extension == '' ? $this->image_extension : $extension);
  }

  public function remove_image($storage, $file_name){
    Storage::disk($storage)->delete($file_name);
  }

  public function save_file($file,$storage,$file_name,$extension){
    $file = str_replace('data:application/pdf;base64,', '', $file);
    $file = str_replace('data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,', '', $file);
    $file = str_replace('data:image/jpeg;base64,', '', $file);
    $file = str_replace('data:image/png;base64,', '', $file);
    $file = str_replace(' ', '+', $file);
    Storage::disk($storage)->put($file_name.'.'.$extension, base64_decode($file));

    return $file_name.'.'.$extension;
  }

  public function copy_file($source_filename, $source_filesystem, $model, $destination_filesystem, $column = 'file_name'){
		$file = Storage::disk($source_filesystem)->get($source_filename);
		if($file){
			$file = Image::make($file);
			$file_name = $this->save_image($file, $destination_filesystem, $model->id);
			$model->{$column} = $file_name;
		}
  }

	public function convert_file($filename, $filesystem, $model, $column = 'file_name'){
		$file = Storage::disk($filesystem)->get($filename);
		if($file){
			$file = Image::make($file);
			$file_name = $this->save_image($file, $filesystem, $model->id, 'webp');
			$model->{$column} = $file_name;
		}
	}
}
