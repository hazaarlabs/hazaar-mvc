<?php

namespace Hazaar\Mail;

use Hazaar\File;
use Hazaar\Mail\Mime\Part;

class Attachment extends Part
{
    public $file;
    private $base64_encode = false;

    public function __construct($file, $name = null)
    {
        parent::__construct();

        if (!$file instanceof File) {
            $file = new File($file);

            if (!$file->exists()) {
                throw new \Exception('Can not attach non-existent file!');
            }
        }

        $this->file = $file;

        parent::setContentType($file->mime_content_type());

        if (!$name) {
            $name = $file->basename();
        }

        $this->setContentType('application/octet-stream; name="'.$name.'"');

        $this->setDescription($name);

        $this->setHeader('Content-Disposition', "attachment; filename=\"{$name}\";\n\tsize=".$file->size().';');

    }

    public function setBase64Encode($base64_encode)
    {
        $this->base64_encode = $base64_encode;
        if (true === $this->base64_encode) {
            $this->setHeader('Content-Transfer-Encoding', 'base64');
        } else {
            $this->setHeader('Content-Transfer-Encoding', '8bit');
        }
    }

    public function getContent()
    {
        if ($this->base64_encode) {
            return base64_encode($this->file->get_contents());
        }

        return $this->file->get_contents();
    }
}
