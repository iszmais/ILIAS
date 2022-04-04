<?php
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL, see docs/LICENSE */

/** @noinspection PhpIncludeInspection */
require_once './Services/WorkflowEngine/interfaces/ilActivity.php';
/** @noinspection PhpIncludeInspection */
require_once './Services/WorkflowEngine/interfaces/ilNode.php';

/**
 * Class ilEventRaisingActivity
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class ilEventRaisingActivity implements ilActivity, ilWorkflowEngineElement
{
    /** @var ilWorkflowEngineElement $context Holds a reference to the parent object */
    private $context;

    /** @var string $event_type Type of the event to be raised. */
    protected string $event_type = '';

    /** @var string $event_name Name of the event to be raised. */
    protected string $event_name = '';

    /** @var  array $fixed_params Fixed params that are always to be sent with the event. Will be overriden by context. */
    protected array $fixed_params = [];

    protected $name;

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function addFixedParam(string $key, $value) : void
    {
        $this->fixed_params[] = array('key' => $key, 'value' => $value);
    }

    /**
     * @return string
     */
    public function getEventName() : string
    {
        return $this->event_name;
    }

    /**
     * @param string $event_name
     */
    public function setEventName(string $event_name) : void
    {
        $this->event_name = $event_name;
    }

    /**
     * @return string
     */
    public function getEventType() : string
    {
        return $this->event_type;
    }

    /**
     * @param string $event_type
     */
    public function setEventType(string $event_type) : void
    {
        $this->event_type = $event_type;
    }

    /**
     * Default constructor.
     *
     * @param ilNode $a_context
     */
    public function __construct(ilNode $a_context)
    {
        $this->context = $a_context;
        $this->event_type = 'Services/WorkflowEngine';
        $this->event_name = 'nondescript';
    }

    /**
     * Executes this action according to its settings.
     * @return void
     *@todo Use exceptions / internal logging.
     */
    public function execute() : void
    {
        global $DIC;
        /** @var ilAppEventHandler $ilAppEventHandler */
        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        $ilAppEventHandler->raise(
            $this->event_type,
            $this->event_name,
            $this->getParamsArray()
        );
    }

    /**
     * @return array
     */
    public function getParamsArray() : array
    {
        // TODO: Get logic for getting values from incoming data associations.

        $params = array();
        $params[] = array('key' => 'context', 'value' => $this);

        return array_merge((array) $this->fixed_params, $params);
    }

    /**
     * Returns a reference to the parent node.
     *
     * @return ilNode Reference to the parent node.
     */
    public function getContext()
    {
        return $this->context;
    }

    public function setName($name) : void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }
}
