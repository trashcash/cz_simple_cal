<?php 

/**
 * manages the build of all recurrant events
 * 
 * @author Christian Zenker <christian.zenker@599media.de>
 */
class Tx_CzSimpleCal_Recurrance_Factory {
	
	/**
	 * the event to build the recurrance for
	 * 
	 * @var Tx_CzSimpleCal_Domain_Model_Event
	 */
	protected $event = null;
	
	/**
	 * build the recurrance for an event
	 * 
	 * @param $event
	 * @return Tx_CzSimpleCal_Domain_Model_Event
	 */
	public function buildRecurranceForEvent($event) {
		if(!$event instanceof Tx_CzSimpleCal_Domain_Model_Event) {
			throw new InvalidArgumentException(sprintf('$event must be of class Tx_CzSimpleCal_Domain_Model_Event in %s::%s', get_class($this), __METHOD__));
		}
		
		$this->event = $event;
		
		/**
		 * a class holding all possible events ordered by their starttime ascending
		 * 
		 * @var Tx_CzSimpleCal_Domain_Collection_EventIndex
		 */
		$events = $this->buildEventTimeline();
		
		/**
		 * a class holding all exceptions
		 */
		$exceptions = $this->buildExceptionTimeline();
		
		return $this->dropExceptionalEvents($events, $exceptions);
		
		
	}
	
	/**
	 * build the recurrance for all events paying no attention to exceptions 
	 * 
	 * @return Tx_CzSimpleCal_Recurrance_Timeline_Event
	 */
	protected function buildEventTimeline() {
		
		$type = $this->event->getRecurranceType();
		if(empty($type)) {
			throw new RuntimeException('The recurrance_type should not be empty.');
		}
		
		$className = 'Tx_CzSimpleCal_Recurrance_Type_'.t3lib_div::underscoredToUpperCamelCase($type);
		
		if(!class_exists($className)) {
			throw new BadMethodCallException(sprintf('The class %s does not exist for creating recurring events.', $className));
		}
		
		$class = t3lib_div::makeInstance($className);
		
		if(!$class instanceof Tx_CzSimpleCal_Recurrance_Type_Base) {
			throw new BadMethodCallException(sprintf('The class %s does not implement the Tx_CzSimpleCal_Recurrance_Type_Base.', get_class($class)));
		}
		
		$eventTimeline = new Tx_CzSimpleCal_Recurrance_Timeline_Event();
		$eventTimeline->setEvent($this->event);
		
		return $class->build($this->event, $eventTimeline);
	}
	
	/**
	 * build the exception timeline
	 * 
	 * @return Tx_CzSimpleCal_Recurrance_Timeline_Exception
	 */
	protected function buildExceptionTimeline() {
		$exceptionTimeline = new Tx_CzSimpleCal_Recurrance_Timeline_Exception();
		
		foreach($this->event->getExceptions() as $exception) {
			
			$type = $exception->getRecurranceType();
			if(empty($type)) {
				throw new RuntimeException('The recurrance_type should not be empty.');
			}
			
			$className = 'Tx_CzSimpleCal_Recurrance_Type_'.t3lib_div::underscoredToUpperCamelCase($type);
			
			if(!class_exists($className)) {
				throw new BadMethodCallException(sprintf('The class %s does not exist for creating recurring events.', $className));
			}
			
			$class = t3lib_div::makeInstance($className);
			
			if(!$class instanceof Tx_CzSimpleCal_Recurrance_Type_Base) {
				throw new BadMethodCallException(sprintf('The class %s does not implement Tx_CzSimpleCal_Recurrance_Type_Base.', get_class($class)));
			}
			t3lib_div::devLog('recurrance', 'cz_simple_cal', 0, array('class' => $className));
			
			$exceptionTimeline = $class->build($exception, $exceptionTimeline);
		}
		return $exceptionTimeline;
	}
	
	/**
	 * drop all events that are blocked by an exception
	 * 
	 * some words on how it works:
	 * 
	 * Basically the idea here is to check every event if it overlaps an exception.
	 * 
	 * To make this algorithm a bit more efficant, these prerequisits are met:
	 *  - the events are ordered by their start-date,
	 *  - the exceptions by their start-date
	 * 
	 * So if we find, that the end-date of an exception is before the current start-date
	 * it is before the start-date of ALL remaining events and we'll just drop it.
	 *  
	 * 
	 * @param Tx_CzSimpleCal_Recurrance_Timeline_Events $events
	 * @param Tx_CzSimpleCal_Recurrance_Timeline_Exception $exceptions
	 * @return Tx_CzSimpleCal_Recurrance_Timeline_Events
	 */
	protected function dropExceptionalEvents($events, $exceptions) {
		$debugEvents = array();
		$debugExceptions = array();
		
		foreach($events as $event) {
			$debugEvents[] = array(
				'start' => date('Y-m-d H:i:s', $event['start']),
				'end' => date('Y-m-d H:i:s', $event['end'])
			);
		}
		
		foreach($exceptions as $exception) {
			$debugExceptions[] = array(
				'start' => date('Y-m-d H:i:s', $exception['start']),
				'end' => date('Y-m-d H:i:s', $exception['end'])
			);
		}
		
		
		foreach($events as $eventKey=>$event) {
			
			if(!$exceptions->hasData()) {
				break;
			}
			
			$exceptions->rewind();
			foreach($exceptions as $exceptionKey=>$exception) {
				
				if($exception['end'] < $eventKey /*eventKey = $event['start']*/) {
					//if: end of exception is before start of event -> delete it as it won't affect any more of the events
					$exceptions->unsetCurrent();
				} elseif($event['end'] < $exceptionKey ) {
					//if: end of event is before start of exception -> none of the following exception will affect this event
					break;
				} else {
					// else: match -> delete this event
					$events->unsetCurrent();
					break;
				}
			}
		}
		return $events;
		
	}
	
}