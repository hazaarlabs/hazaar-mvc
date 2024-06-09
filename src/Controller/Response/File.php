<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Application;
use Hazaar\Controller\Response\HTTP\OK;
use Hazaar\Date;
use Hazaar\File as FileObject;
use Hazaar\File\Manager;

class File extends OK
{
    /**
     * Default cache control.
     *
     * Public with max age is 5 minutes
     *
     * @var array<string,bool|int|string>
     */
    public static array $__default_cache_control_directives = [
        'public' => false,
        'max-age' => 300,
    ];
    private ?FileObject $file;
    private ?Manager $manager;
    private ?Date $fmtime;

    /**
     * Byte-Order-Mark.
     *
     * This allows a byte-order-mark to be output at the beginning of the file content if one does not already exist.
     */
    private ?string $bom = null;

    /**
     * Charset map.
     *
     * This is a map of charsets to their respective byte-order-marks.
     *
     * @var array<string,string>
     */
    private array $charsetMap = [
        'utf-8' => 'EFBBBF',
        'utf-16' => 'FEFF',
        'utf-16be' => 'FEFF',
        'utf-16le' => 'FFFE',
        'utf-32' => '0000FEFF',
        'utf-32be' => '0000FEFF',
        'utf-32le' => 'FFFE0000',
    ];

    /**
     * \Hazaar\File Constructor.
     *
     * @param null|FileObject|string $file either a string filename to use or a \Hazaar\File object
     */
    public function __construct(null|FileObject|string $file = null, ?Manager $manager = null)
    {
        $this->manager = $manager;
        parent::__construct();
        $this->initialiseCacheControl();
        if (null !== $file) {
            $this->load($file, $manager);
        }
    }

    public function initialiseCacheControl(): bool
    {
        $cache_config = Application::getInstance()->config->get('http.cacheControl', self::$__default_cache_control_directives, true);
        if ($cacheControlHeader = ake(apache_request_headers(), 'Cache-Control')) {
            $replyable = ['no-cache', 'no-store', 'no-transform'];
            $parts = explode(',', $cacheControlHeader);
            foreach ($parts as $part) {
                if ('max-age' === substr($part, 0, 7)) {
                    $cache_config->set('max-age', (int) substr($part, strpos($part, '=', 7) + 1));

                    break;
                }
                if (in_array(strtolower(trim($part)), $replyable)) {
                    $cache_config->set('reply', $part);
                }
            }
        }
        $cache_control = [];
        if ($cache_config->has('reply')) {
            $cache_control[] = $cache_config->get('reply');
        } elseif (true === $cache_config->get('no-store')) {
            $cache_control[] = 'no-store';
        } elseif (true === $cache_config->get('no-cache')) {
            $cache_control[] = 'no-cache';
        } elseif ($cache_config->has('public')) {
            $cache_control[] = $cache_config->get('public') ? 'public' : 'private';
        } elseif ($cache_config->has('private')) {
            $cache_control[] = $cache_config->get('private') ? 'private' : 'public';
        }
        if ($cache_config->has('max-age')
            && !('no-cache' === $cache_config->reply
                || 'no-store' === $cache_config->reply
                || true === $cache_config->get('no-cache')
                || true === $cache_config->get('no-store'))) {
            $cache_control[] = 'max-age='.$cache_config->get('max-age');
        }
        if (count($cache_control) > 0) {
            return $this->setHeader('Cache-Control', implode(', ', $cache_control));
        }

        return false;
    }

    public function load(FileObject|string $file, ?Manager $manager = null): bool
    {
        if (!$manager) {
            $manager = $this->manager;
        }
        $this->file = ($file instanceof FileObject) ? $file : new FileObject($file, $manager);
        if (!($this->file->exists() || $this->hasContent())) {
            return false;
        }
        $this->setContentType($this->file->mimeContentType());
        $this->setLastModified($this->file->mtime());

        return true;
    }

    public function setContent(mixed $data, ?string $contentType = null): void
    {
        if ($data instanceof FileObject) {
            $this->file = $data;
        } elseif (!$this->file) {
            $this->file = new FileObject(null);
        }
        if ($contentType) {
            $this->file->setMimeContentType($this->contentType = $contentType);
        }
        $this->file->setContents($data);
    }

    public function getContent(): string
    {
        if ($this->file) {
            $content = $this->file->getContents();
        } else {
            $content = parent::getContent();
        }
        foreach ($this->charsetMap as $bom) {
            if (substr($content, 0, strlen($bom)) !== $bom) {
                continue;
            }
            $this->bom = null;

            break;
        }

        return $this->bom.$content;
    }

    public function getContentLength(): int
    {
        if ($this->file) {
            return $this->file->size();
        }

        return 0;
    }

    public function hasContent(): bool
    {
        if ($this->file) {
            return $this->file->size() > 0;
        }

        return false;
    }

    public function setUnmodified(Date $ifModifiedSince): bool
    {
        if (!$this->file) {
            return false;
        }
        if (($this->fmtime ? $this->fmtime->sec() : $this->file->mtime()) > $ifModifiedSince->getTimestamp()) {
            return false;
        }
        $this->setStatus(304);

        return true;
    }

    public function setLastModified(Date|int $fmtime): void
    {
        if (!$fmtime instanceof Date) {
            $fmtime = new Date($fmtime, 'UTC');
        }
        $this->fmtime = $fmtime;
        $this->setHeader('Last-Modified', gmdate('r', $this->fmtime->sec()));
    }

    public function getLastModified(): string
    {
        return $this->getHeader('Last-Modified');
    }

    /**
     * @param null|array<mixed> $matches
     * @param 0|256|512|768     $flags
     */
    public function match(string $pattern, ?array &$matches = null, int $flags = 0, int $offset = 0): false|int
    {
        return preg_match($pattern, $this->getContent(), $matches, $flags, $offset);
    }

    public function replace(string $pattern, string $replacement, int $limit = -1, ?int &$count = null): void
    {
        $this->setContent(preg_replace($pattern, $replacement, $this->getContent(), $limit, $count));
    }

    public function setDownloadable(bool $toggle = true, ?string $filename = null): void
    {
        if (!$filename) {
            $filename = $this->file->basename();
        }
        if ($toggle) {
            $this->setHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } else {
            $this->setHeader('Content-Disposition', 'inline; filename="'.$filename.'"');
        }
    }

    public function setContentType(?string $type = null): void
    {
        parent::setContentType($type);
        if (($colon_pos = strpos($this->contentType, ';')) === false) {
            return;
        }
        $options = array_change_key_case(array_unflatten(trim(substr($this->contentType, $colon_pos + 1))), CASE_LOWER);
        if (!array_key_exists('charset', $options)) {
            return;
        }
        $options = array_map('strtolower', array_map('trim', $options));
        if (!array_key_exists($options['charset'], $this->charsetMap)) {
            return;
        }
        $this->bom = pack('H*', $this->charsetMap[$options['charset']]);
    }

    public function getContentType(): string
    {
        return $this->contentType ? $this->contentType : $this->file->mimeContentType();
    }

    public function getFile(): FileObject
    {
        return $this->file;
    }

    public function fileExists(): bool
    {
        return $this->file->exists();
    }
}
