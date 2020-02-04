<?php

/** @noinspection PhpComposerExtensionStubsInspection */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Exception\ZipException;

/**
 * Class EpubInfo.
 *
 * @see http://idpf.org/epub/30/spec/epub30-publications.html
 */
class EpubInfo
{
    /** @var string|null */
    private $title;

    /** @var string|null */
    private $creator;

    /** @var string|null */
    private $language;

    /** @var string|null */
    private $publisher;

    /** @var string|null */
    private $description;

    /** @var string|null */
    private $rights;

    /** @var string|null */
    private $date;

    /** @var string|null */
    private $subject;

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

    /**
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string|null
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @return string|null
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @return string|null
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getRights()
    {
        return $this->rights;
    }

    /**
     * @return string|null
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return string|null
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return array
     */
    public function toArray()
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
