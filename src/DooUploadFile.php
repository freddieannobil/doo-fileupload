<?php

namespace Doowebdev;


class DooUploadFile {


    protected $destination;
    protected $messages = [];
    protected $maxSize = 51200;
    protected $newName;
    protected $permittedTypes = [
        'image/jpeg',
        'image/pjpeg',
        'image/gif',
        'image/png',
        'image/webp'
    ];
    protected $typeCheckingOn = true;
    protected $notTrusted = ['bin', 'cgi', 'exe', 'js', 'pl', 'php', 'py', 'sh'];
    protected $suffix = '.upload';
    protected $renameDuplicates;


    public function  __construct( $uploadFolder )
    {
        if( !is_dir( $uploadFolder ) || !chmod( $uploadFolder, 0755) )
        {
            throw new \Exception("$uploadFolder must be a valid writable folder.");
        }

        if( $uploadFolder[strlen( $uploadFolder )-1] != '/')
        {
            $uploadFolder .= '/';
        }
        $this->destination = $uploadFolder;
    }

    public function setMaxSize( $bytes )
    {
        $serverMax = self::convertToBytes( ini_get('upload_max_filesize') );
       if( $bytes > $serverMax )
       {
           throw new \Exception('Maximum size cannot exceed sever limit for individual files: ' .
          self::convertFromBytes( $serverMax ) );
       }
        if( is_numeric( $bytes ) && $bytes > 0 )
        {
           $this->maxSize = $bytes;
        }
    }

    public static function convertToBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        if (in_array($last, array('g', 'm', 'k'))){
            switch ($last) {
                case 'g':
                    $val *= 1024;
                case 'm':
                    $val *= 1024;
                case 'k':
                    $val *= 1024;
            }
        }
        return $val;
    }

    public static function convertFromBytes($bytes)
    {
        $bytes /= 1024;
        if ($bytes > 1024) {
            return number_format($bytes/1024, 1) . ' MB';
        } else {
            return number_format($bytes, 1) . ' KB';
        }
    }

    public function allowAllTypes( $suffix = null )
    {
        $this->typeCheckingOn = false;
        if( !is_null( $suffix ))
        {
            if( strpos( $suffix , '.' ) === 0 || $suffix == '' )
            {
                $this->suffix = $suffix;
            }else{
               $this->suffix = ".$suffix";
            }
        }
    }

    public function upload( $renameDuplicates = true )
    {
       $this->renameDuplicates = $renameDuplicates;
        $uploaded = current( $_FILES );
        if( $this->checkFile( $uploaded ) )
        {
            $this->moveFile( $uploaded );
        }
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getFilename()
    {
        return $this->newName;
    }

    protected function checkFile( $file )
    {
        if( $file['error'] != 0 )
        {
            $this->getErrorMessage( $file );
            return false;
        }

        if( !$this->checkSize( $file ) )
        {
           return false;
        }
        if( $this->typeCheckingOn )
        {
            if ( !$this->checkType( $file ) )
            {
                return false;
            }
        }
        $this->checkName( $file );

        return true;
    }

    protected function getErrorMessage( $file )
    {
        switch( $file['error'] ){
            case 1:
            case 2:
                $this->messages[] = $file['name'].' is too big: (max:
                '.self::convertFromBytes( $this->maxSize).').';
            break;
            case 3:
                $this->messages[] = $file['name'] .'was partially uploaded.';
                break;
            case 4:
                $this->messages[] = 'No file submitted.';
                break;
            default:
                $this->messages[] = 'There wa a problem uploading '.$file['name'];
        }

    }

    protected function checkSize( $file )
    {
        if( $file['size'] == 0 )
        {
            $this->messages[] = $file['name'] .' is empty.';
            return false;
        }elseif( $file['size'] > $this->maxSize ){
            $this->messages[] = $file['name'] .' exceeds the maximum file size ('
                .self::convertFromBytes( $this->maxSize).').';
            return false;
        }else{
            return true;
        }
    }

    protected function checkType( $file )
    {
        if( in_array( $file['type'], $this->permittedTypes ) )
        {
            return true;
        }else{
            $this->messages[] = $file['name']. ' is not a permitted type of file.';
            return false;
        }
    }

    protected  function checkName( $file )
    {
        $this->newName = null;
        $nospaces = str_replace(' ', '_', $file['name']);
        if( $nospaces != $file['name'] )
        {
            $this->newName = $nospaces;
        }
        $nameparts = pathinfo( $nospaces );
        $extension = isset( $nameparts['extension'] ) ? $nameparts['extension'] : '';
        if( !$this->typeCheckingOn && !empty( $this->suffix ) )
        {
            if( in_array( $extension, $this->notTrusted ) || empty( $extension ) )
            {
                $this->newName = $nospaces . $this->suffix;
            }
        }
        if( $this->renameDuplicates )
        {
            $name = isset( $this->newName ) ? $this->newName : $file['name'];
            $existing = scandir( $this->destination );
            if( in_array( $name, $existing ))
            {
                $i = 1;
                    do{
                        $this->newName = $nameparts['filename'] .'_'.$i++;
                        if( !empty( $extension ) )
                        {
                            $this->newName .= ".$extension";
                        }
                        if( in_array( $extension, $this->notTrusted ) )
                        {
                            $this->newName .= $this->suffix;
                        }
                    }while( in_array( $this->newName, $existing ) );
            }
        }
    }

    protected function moveFile( $file )
    {
        $filename = isset( $this->newName ) ? $this->newName : $file['name'];
        $success = move_uploaded_file( $file['tmp_name'], $this->destination . $filename );
        if( $success ) {
            $result = $file['name'] . ' was uploaded successfully.';
            if (!is_null($this->newName)) {
                $result .= ', and was renamed ' . $this->newName;
            }
            $result .= '.';
            $this->messages[] = $result;
        }else{
            $this->messages[] = 'Could not upload ' .$file['name'];
        }
    }








} 