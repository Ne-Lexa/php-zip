<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Exception\ZipException;

/**
 * Class EpubInfo.
 *
 * @see http://idpf.org/epub/30/spec/epub30-publications.html
 */
class EpubInfo
{
    private ?string $title;

    private ?string $creator;

    private ?string $language;

    private ?string $publisher;

    private ?string $description;

    private ?string $rights;

    private ?string $date;

    private ?string $subject;

    /**
     * EpubInfo constructor.
     *
     * @param $xmlContents
     *
     * @throws ZipException
     */
    public function __construct($xmlContents)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlContents);
        $xpath = new \DOMXpath($doc);
        $xpath->registerNamespace('root', 'http://www.idpf.org/2007/opf');
        $metaDataNodeList = $xpath->query('//root:metadata');

        if (\count($metaDataNodeList) !== 1) {
            throw new ZipException('Invalid .opf file format');
        }
        $metaDataNode = $metaDataNodeList->item(0);

        $title = $xpath->evaluate('string(//dc:title)', $metaDataNode);
        $creator = $xpath->evaluate('string(//dc:creator)', $metaDataNode);
        $language = $xpath->evaluate('string(//dc:language)', $metaDataNode);
        $publisher = $xpath->evaluate('string(//dc:publisher)', $metaDataNode);
        $description = $xpath->evaluate('string(//dc:description)', $metaDataNode);
        $rights = $xpath->evaluate('string(//dc:rights)', $metaDataNode);
        $date = $xpath->evaluate('string(//dc:date)', $metaDataNode);
        $subject = $xpath->evaluate('string(//dc:subject)', $metaDataNode);

        $this->title = empty($title) ? null : $title;
        $this->creator = empty($creator) ? null : $creator;
        $this->language = empty($language) ? null : $language;
        $this->publisher = empty($publisher) ? null : $publisher;
        $this->description = empty($description) ? null : $description;
        $this->rights = empty($rights) ? null : $rights;
        $this->date = empty($date) ? null : $date;
        $this->subject = empty($subject) ? null : $subject;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getCreator(): ?string
    {
        return $this->creator;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRights(): ?string
    {
        return $this->rights;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'creator' => $this->creator,
            'language' => $this->language,
            'publisher' => $this->publisher,
            'description' => $this->description,
            'rights' => $this->rights,
            'date' => $this->date,
            'subject' => $this->subject,
        ];
    }
}
