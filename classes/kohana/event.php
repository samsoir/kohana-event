<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Process queuing/execution class. Allows an unlimited number of callbacks
 * to be added to 'events'. Events can be run multiple time, and can also
 * process event-specific data.
 * 
 * This library has been ported from Kohana 2.x purely for handling custom
 * event stacks. Kohana 3.x does not have a hooks system so all events must
 * be implemented.
 *
 * @package    Kohana
 * @category   Cache
 * @author     Kohana Team
 * @copyright  (c) 2009-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
abstract class Kohana_Event {

	/**
	 * @var  array containing events and callbacks
	 */
	protected static $_events = array();

	/**
	 * @var  array containing events that have run
	 */
	protected static $_has_run = array();

	/**
	 * @var  mixed data to be processed
	 */
	public static $data;

	/**
	 * Add a callback to the event stack
	 *
	 * @param   string   name 
	 * @param   array    callback  (http://php.net/callback)
	 * @param   boolean  unique prevents duplicate events
	 * @return  boolean
	 */
	public static function add($name, array $callback, $unique = FALSE)
	{
		if ( ! isset(Event::$_events[$name]))
		{
			// Create an empty event for undefined events
			Event::$_events[$name] = array();
		}
		elseif ($unique and in_array($callback, Event::$_events[$name], TRUE))
		{
			// Event already exists
			return FALSE;
		}

		// Add the event
		Event::$_events[$name][] = $callback;

		return TRUE;
	}

	/**
	 * Add a callback to an event queue, before a given event.
	 *
	 * @param   string   name 
	 * @param   array    existing 
	 * @param   array    callback 
	 * @return  boolean
	 */
	public static function add_before($name, array $existing, array $callback)
	{
		if (empty(Event::$_events[$name]) or FALSE === ($key = array_search($existing, Event::$_events[$name], TRUE)))
		{
			// No need to insert, just add
			return Event::add($name, $callback);
		}

		// Insert the event immediately before the existing event
		return Event::_insert_event($name, $key, $callback);
	}

	/**
	 * Add a callback to an event queue, after a given event.
	 *
	 * @param   string   name 
	 * @param   array    existing 
	 * @param   array    callback 
	 * @return  boolean
	 */
	public static function add_after($name, array $existing, array $callback)
	{
		if (empty(Event::$_events[$name]) or FALSE === ($key = array_search($existing, Event::$_events[$name], TRUE)))
		{
			// No need to insert, just add
			return Event::add($name, $callback);
		}

		// Insert the event immediately after the existing event
		return Event::_insert_event($name, $key++ , $callback);
	}

	/**
	 * Replaces an event with another event.
	 *
	 * @param   string   name 
	 * @param   array    existing 
	 * @param   array    callback 
	 * @return  boolean
	 */
	public static function replace($name, array $existing, array $callback)
	{
		if (empty(Event::$_events[$name]) or FALSE === ($key = array_search($existing, Event::$_events[$name], TRUE)))
			return FALSE;

		if ( ! in_array($callback, Event::$_events[$name], TRUE))
		{
			Event::$_events[$name][$key] = $callback;
			return TRUE;
		}

		// Remove event from the stack
		unset(Event::$_events[$name][$key]);

		// Reset the array to preserve ordering
		Event::$_events[$name] = array_values(Event::$_events[$name]);

		return TRUE;
	}

	/**
	 * Get all callbacks for an event.
	 *
	 * @param   string   name 
	 * @return  array
	 */
	public static function get($name)
	{
		return empty(Event::$_events[$name]) ? array() : Event::$_events[$name];
	}

	/**
	 * Clear some or all callbacks from an event.
	 *
	 * @param   string   name 
	 * @param   array    callback 
	 * @return  void
	 */
	public static function clear($name = NULL, array $callback = NULL)
	{
		if (NULL === $name and NULL === $callback)
		{
			// Clear all events
			Event::$_events = array();
			return;
		}

		if (NULL === $callback)
		{
			// Clear named events
			Event::$_events[$name] = array();
			return;
		}

		// If the name does not exist or the callback cannot be found, return
		if ( ! isset(Event::$_events[$name]) or FALSE === ($key = array_search($callback, Event::$_events[$name], TRUE)))
			return;

		// Unset the callback
		unset(Event::$_events[$name][$key]);

		// Reset the array to preserve ordering
		Event::$_events[$name] = array_values(Event::$_events[$name]);

		return;
	}

	/**
	 * Execute all of the callbacks attached to an event.
	 *
	 * @param   string   name 
	 * @param   mixed    data 
	 * @return  void
	 */
	public static function run($name, & $data = NULL)
	{
		// Event has been run
		Event::$_has_run[$name] = TRUE;

		if (empty(Event::$_events[$name]))
			return;

		Event::$data = & $data;
		$callbacks = Event::get($name);

		foreach ($callbacks as $callback)
			call_user_func_array($callback, array(&$data));

		$clear_data = NULL;
		Event::$data = & $clear_data;

		return;
	}

	/**
	 * Check if an event has run
	 *
	 * @param   string   name 
	 * @return  boolean
	 */
	public static function has_run($name)
	{
		return isset(Event::$_has_run[$name]);
	}

	/**
	 * Inserts a new event at a specfic key location.
	 *
	 * @param   string   name 
	 * @param   string   key 
	 * @param   array    callback 
	 * @return  boolean
	 */
	protected static function _insert_event($name, $key, array $callback)
	{
		if (in_array($callback, Event::$_events[$name], TRUE))
			return FALSE;

		Event::$_events[$name] = array_merge(
			array_slice(Event::$_events[$name], 0, $key),
			array($callback),
			array_slice(Event::$_events[$name], $key)
		);

		return TRUE;
	}

	/**
	 * Ensures this class remains a singleton
	 */
	final protected function __construct() {}
}
