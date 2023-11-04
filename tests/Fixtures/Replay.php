<?php

namespace SimpleNeo4j\Tests\Fixtures;

use SimpleNeo4j\ORM;

class Replay extends ORM\NodeModelAbstract {

    const ENTITY = 'Replay';

    const STATE_CREATED = 1;

    const STATE_COMPLETE = 2;

    const STATE_PROCESS_ERROR = 3;

    const STATE_PROCESSING = 4;

    const ORDER_STATUS_NONE = 0;

    const ORDER_STATUS_PURCHASED = 1;

    const TYPE_FULL = 1;

    const TYPE_INSTANT = 2;

    protected $_id = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_PRIMARY => true,
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_AUTO_INCREMENT,
        ]
    ];

    protected $_state = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => self::STATE_CREATED,
        ]
    ];

    protected $_ordered_status = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => self::ORDER_STATUS_NONE,
        ]
    ];

    protected $_duration = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => 45,
        ]
    ];

    protected $_price = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => 0.50,
        ]
    ];

    protected $_sport_id = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => null,
        ]
    ];

    protected $_real_duration = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => null,
        ]
    ];

    protected $_predicted_start = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => null,
        ]
    ];

    protected $_created_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_CREATED_ON,
        ]
    ];

    protected $_modified_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_MODIFIED_ON,
        ]
    ];

    protected $_filename = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_STRING,
        ]
    ];

    protected $_processed_filename = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_STRING,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => null,
        ]
    ];

    protected $_key = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_STRING,
        ]
    ];

    protected $_thumb_filename = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_STRING,
        ]
    ];

    protected $_type = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => self::TYPE_FULL,
        ]
    ];

    protected $_camera = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasReplay::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => CameraDevice::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'incoming',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
        ]
    ];

    protected $_court = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasReplay::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => Court::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'incoming',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
        ]
    ];

    protected $_session = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasReplay::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => Session::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'incoming',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
        ]
    ];

    public function getId() : int {

        return $this->_id;

    }

    public function getOriignalFullVideoCdnUrl() : string {

        return 'https://cdn.savemyplay.com/' . $this->getFilename();

    }

    public function getFullVideoCdnUrl() : string {

        if ($this->getState() == self::STATE_COMPLETE) {
            $processed_filename = $this->getProcessedFilename();

            if ($processed_filename) {
                return 'https://cdn.savemyplay.com/' . $processed_filename;
            }
        }

        return $this->getOriignalFullVideoCdnUrl();
    }

    public function getCreatedTime() : int {

        return $this->_created_time;

    }

    public function getState() : int {

        if ($this->_state === self::STATE_CREATED || $this->_state === self::STATE_PROCESSING) {
            if (!$this->isInstant() && $this->_created_time < (time() - (60 * 30))) {
                return self::STATE_PROCESS_ERROR;
            }
        }

        return $this->_state;
    }

    public function getFilename() : string {

        return 'replays/' . $this->getId() . '_' . $this->_filename;

    }

    public function getProcessedFilename() : ?string {

        if (!$this->_processed_filename) {
            return null;
        }

        if ($this->_processed_filename === 'test.mp4') {
            return 'replays/' . $this->_processed_filename;
        }

        return 'replays/' . $this->getId() . '_' . $this->_processed_filename;

    }

    public function getEndTime() : int {

        return $this->_created_time;

    }

    public function getStartTime() : int {

        return $this->getEndTime() - $this->getDuration();

    }

    public function getThumbnailUrl() : string {

        $state = $this->getState();

        if ($state === self::STATE_COMPLETE) {
            return 'https://cdn.savemyplay.com/' . $this->getThumbnailFilenameWithPath();
        } else if ($state === self::STATE_CREATED || $state === self::STATE_PROCESSING) {
            return 'https://cdn.savemyplay.com/assets/processing.gif';
        }

        return '';

    }

    public function getPosterUrl() : string {

        $state = $this->getState();

        if ($state === self::STATE_COMPLETE) {
            return 'https://cdn.savemyplay.com/' . $this->getPosterFilenameWithPath();
        } else if ($state === self::STATE_CREATED || $state === self::STATE_PROCESSING) {
            return 'https://cdn.savemyplay.com/assets/processing.gif';
        }

        return '';

    }

    public function getOrderStatus() : int {

        return $this->_ordered_status;

    }

    public function setOrderStatus(int $status) {

        $this->_ordered_status = $status;

    }

    public function getKey() : string {

        return $this->_key;

    }

    public function getCamera() : CameraDevice {

        /** @var HasReplay $rel */
        $rel = $this->_camera[0];

        return $rel->getStartNode();

    }

    public function getCourt() : Court {

        /** @var HasReplay $rel */
        $rel = $this->_court[0];

        return $rel->getStartNode();

    }

    public function getSlug() : string {

        $slug_generator = new SlugGenerator();

        return $this->getId()
            . '-' . $this->getKey()
            . '-'
            . $slug_generator->generate($this->getCamera()->getFacility()->getName());

    }

    public function isKey(string $key) : bool {

        return $this->_key === $key;

    }

    public function getFullUrl() : string {

        return 'https://savemyplay.com/replays/' . $this->getSlug();

    }

    public function getRelativeUrl() : string {

        return '/replays/' . $this->getSlug();

    }

    public function getPrice() : float {

        return $this->_price;

    }

    public function isAiEnabled() : bool {

        return $this->_ai_enabled;

    }

    public function getPreviousAndNext(array $replays, Replay $current_replay) : array {

        $cur_index = null;

        $info = [
            'prev' => null,
            'next' => null,
        ];

        foreach ($replays as $index => $replay) {
            if ($replay->getId() === $current_replay->getId()) {
                $cur_index = $index;
                break;
            }
        }

        $prev_index = $cur_index - 1;
        $next_index = $cur_index + 1;

        if ($prev_index >= 0) {
            $info['prev'] = $replays[$prev_index]->toArray();
        }

        if ($next_index < count($replays)) {
            $info['next'] = $replays[$next_index]->toArray();
        }

        return $info;

    }

    public function setState(int $state) {

        $this->_state = $state;

    }

    public function getRealDuration() : ?float {

        return $this->_real_duration;

    }

    public function getPredictedStartTime() : ?float {

        return $this->_predicted_start;

    }

    public function setPredictedStartTime(float $start_time) {

        $this->_predicted_start = $start_time;

    }

    public function setProcessedFilename(string $filename) {

        $this->_processed_filename = $filename;

    }

    public function setThumbFilename(string $thumb_filename) {

        $this->_thumb_filename = $thumb_filename;

    }

    public function getDuration() : float {

        $real_duration = $this->getRealDuration();

        if ($real_duration === null) {
            return $this->_duration;
        }

        return $real_duration;

    }

    public function getDurationString() : string {

        $duration = $this->getDuration();

        return gmdate("H:i:s", ceil($duration));

    }


    public function hasError() : bool {

        return $this->getState() === self::STATE_PROCESS_ERROR;

    }

    public function setRealDuration(float $duration) {

        $this->_real_duration = $duration;

    }

    public function isValid() : bool {

        return $this->getState() !== self::STATE_PROCESS_ERROR;

    }

    public function getThumbnailFilename() : string {

        if ($this->_thumb_filename === 'test.jpg') {
            return 'med_' . $this->_thumb_filename;
        }

        return $this->getId() . 'med' . $this->_thumb_filename;

    }

    public function getPosterFilename() : string {

        if ($this->_thumb_filename === 'test.jpg') {
            return $this->_thumb_filename;
        }

        return $this->getId() . '_' . $this->_thumb_filename;

    }

    public function getThumbnailFilenameWithPath() : string {

        return 'thumbs/' . $this->getThumbnailFilename();

    }

    public function getPosterFilenameWithPath() : string {

        return 'thumbs/' . $this->getPosterFilename();

    }

    public function isPurchased() : bool {

        return $this->getOrderStatus() === self::ORDER_STATUS_PURCHASED;

    }

    public function isInstant() : bool {

        return $this->_type === self::TYPE_INSTANT;

    }

    public function getSession() : Session {

        /** @var HasReplay $rel */
        $rel = $this->_session[0];

        return $rel->getStartNode();

    }
}