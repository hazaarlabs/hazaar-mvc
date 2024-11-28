<?php

namespace Hazaar\Mail;

use Hazaar\File;
use Hazaar\Mail\Mime\Part;

class Attachment extends Part
{
    public function __construct(File $file, ?string $name = null, bool $base64_encode = true)
    {
        parent::__construct();
        parent::setContentType($file->mimeContentType());
        if (!$name) {
            $name = $file->basename();
        }
        $this->setContentType('application/octet-stream; name="'.$name.'"');
        $this->setDescription($name);
        $this->setHeader('Content-Disposition', "attachment; filename=\"{$name}\";\n\tsize=".$file->size().';');
        if ($base64_encode) {
            $this->setHeader('Content-Transfer-Encoding', 'base64');
            $this->content = base64_encode($file->getContents());
        } else {
            $this->content = $file->getContents();
        }
    }
}
