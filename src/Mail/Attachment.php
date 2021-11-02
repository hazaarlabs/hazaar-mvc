<?php

namespace Hazaar\Mail;

class Attachment extends \Hazaar\Mail\Mime\Part {

    private $file;

    function __construct($file, $name = null, $base64_encode = true) {

        parent::__construct();

        if(!$file instanceof \Hazaar\File){

            $file = new \Hazaar\File($file);

            if(!$file->exists())
                throw new \Exception('Can not attach non-existent file!');

        }

        $this->file = $file;

        parent::setContentType($file->mime_content_type());

        if(!$name)
            $name = $file->basename();

        $this->setContentType('application/octet-stream; name="' . $name . '"');

        $this->setDescription($name);

        $this->setHeader('Content-Disposition', "attachment; filename=\"$name\";\n\tsize=" . $file->size() .';');

        if($base64_encode){

            $this->setHeader('Content-Transfer-Encoding', 'base64');

            $this->content = base64_encode($file->get_contents());

        }else{

            $this->content = $file->get_contents;

        }

    }

}
