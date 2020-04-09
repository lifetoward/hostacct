<?php
/**
* For use exclusively by Instance!
* Here we handle the loading and storing of the fields within an instance. That means this is whery the MAGICAL QUERY BUILDING goes on for Synthesis data objects.
* What we were originally trying to accomplish with the data loading and storing:
*	- Need to be able write an overriding loading function (or few) which includes all the joins and such.
*	- That overriding function and the parent's loader need to be able to make use of field-specific hints so that to a large extent
* 		overriding the loader is unnecessary and flows naturally from information we already know about the fields.
* 	- Loaders and Storers need to deal with included elements seamlessly and can do so very efficiently, skipping unmodified ones.
* One open question is where formal filters enter into the picture. For now we'll implement below that level, doing filtering with WHERE clauses.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
trait LoadAndStore
{
	/**
	* Call this method to load an entire instance object's data from the database.
	*
	* Be sure to call it via the intended class name, ie. $class::loadInstanceData(), where $class is a subclass of Instance.
	* Overall, fields are returned as columns prefixed by their place in the reference chain.
	* The query results will include columns for and be GROUP'd BY every key defined for the instance. These columns will be named '_$keyName'.
	* The query results will include columns for any system-defined keys provided in the $keysel as described below. These columns will be named '_$keyName'. Selectors for non-instance keys must be SQL predicates (including operator)
	* Unless the load is a "short" load as can occur for referenced tables when $args['identifying'] is set, the query results will include a _full column set to 1
	* The query results will include all the columns from tables unspecified and referenced via keys other than the first key. These columns will be prefixed with the key name like this: _$keyName_$refFieldName
	* All fields included via reference type fields (include, fieldset, require, belong, refer) will be prefixed with $fieldName_ and this can be recursive.
	* Referenced objects of type belong and refer are "short" loaded; those of type include, fieldset, and require are loaded with all their fields (if the referring object is)
	* For fields of type=instance, no chaining to the referenced field is performed and the field value returned will simply be the handle to an instance with must be loaded separately later.
	*
	* @param mixed $keysel With this variably interpreted argument one can specify the loading of system-defined fields, or provide selection criteria for defined instance keys or other system-defined fields.
	*	If you provide a numeric scalar, it will be interpreted as a unique selector for the FIRST (or only) defined instance key (ie. referent for Relation, id for Element or Fieldset)
	*	If you provide a numerically-indexed array, the values in the array will be assumed to be numeric element ids to match against the FIRST defined instance key values... ie. WHERE {keytable}._id IN( {id_list} )
	*	if you provide a string-indexed array, the string indexes will be interpreted as key names:
	*		keys found in the instance defined key list will be selected by whatever value is provided for that index. The rules for this selection are as described above for the implied FIRST key.
	*		keys not found in the instance-defined key list will be included in the query result, but the selector, if provided, must be an SQL WHERE predicate. (By 'predicate' we mean only the operator and its object should be provided.)
	* @param mixed[] $args We accept the following optional arguments in an associative array:
	* 	where = a mysql WHERE clause which is used to filter the result on any of the fields assembled. Note that to ensure unique identification of the fields you wish to use, each reference to a field (db column) must
	*		be prefixed by an appropriate table specifier. For fields in the primary table, the prefix should be "`{}`.". For fields which are part of referenced elements (joined tables), you must follow the table naming conventions employed
	*		by the query builder. The rule is this: For any field NOT in the top-level, prepend the path of all reference fields leading to this one with _ as the path separator.
	*		as $focal->requirefield->includefield->referfield->targetfield (or equivalently $focal->requirefield->referfield->targetfield) would be specified in a where clause like this:
	*		"`{}_requirefield_includefield_referfield`.`targetfield`" In actual practice of course this is a pretty bizarre case. But deeply embedded fields are sometimes needed at the top level and this is how you find them.
	*		Note that in a future version we will get rid of SQL where clauses entirely at the API level and we'll have some more enlightened way to specify filtering selectors like this "{requirefield.includefield.referfield.targetfield}"
	* 	sortfield = the name of a field to pull to the top of the sort relevance - sorting precedence and direction is defined in the field list; We may need to work on this to ensure proper sorting for included fields. Only top-level fields may be
	*		designated for first sort selection, so that means field in the primary instance, those in the relative instances, and those in included instances. A non-include reference cannot be sorted-through, but the field itself can be.
	* 	reverse = boolean indication of whether to swap the sort order for the whole result.
	* 	limit = maximum number of records to obtain
	* 	start = ordinally first record of the selected set whereat to begin returning data
	*   identifying = boolean; if set, the query will only include fields which flagged as identifying; other fields are skipped to avoid unnecessary query size. When set, we do NOT fetch the _loadtime stamp.
	* @return LoadingStatement We return the loading statement after its query has been executed.
	*/
	protected static function loadInstanceData( $keysel, array $args = array() )
	{
		$class = get_called_class();
		$q = LoadingStatement::newStatement($class, $GLOBALS['root'], $args['sortfield'], $args['reverse']);
		$keyDefs = is_array($class::$keys) ? $class::$keys : array($class::$keys=>'*self');

		// Here we handle key selection as requested in the call
		$keysToSpecify = $keyDefs; // We'll drop these as they are specified and if none are left, we have a solo situation.
		if ($keysel) {
			if ((is_array($keysel) && (is_numeric(array_keys($keysel)[0]))) || is_numeric($keysel))
				$keysel = array(array_keys($keyDefs)[0]=>$keysel); // special case of numeric scalar or numeric list of IDs... we just apply them as the first defined key
			if (!is_array($keysel))
				throw new ErrorException("Invalid key selection argument!");
			foreach ($keysel as $key=>$selector) {
				if (!array_key_exists($key, $keyDefs) && isset($class::$sysvals) && in_array($class::$sysvals)) {
					$q->addColumn("_$key");
					if ($selector)
						$q->addSQLSelector("`{}`._$key $selector");
				} else if (is_array($selector) && count($selector) > 0)
					$q->addSQLSelector("`{}`._$key IN (". implode(',', $selector) .")");
				else if (is_numeric($selector) && $selector > 0) {
					$q->addSQLSelector("`{}`._$key = $selector");
					unset($keysToSpecify[$key]);
				}
			}
		}
		$solo = count($keysToSpecify) < 1;
		$q->setSorting(!$solo);

		// We always GROUP BY all instance-defined keys in the query
		// We join the table(s) and columns of all unspecified but the first defined key; the first-defined key is assumed to be THIS or IN HAND (referent)
		// When a non-first key is specified, we join it as "identifying", otherwise in full.
		// We leave the inclusion of the key fields in the query to the recursive aspect.
		foreach ($keyDefs as $key=>$kclass) {
			$q->addGrouping("_$key");
			if (!$solo && $keysToSpecify[$key])
				$keyOrdering[] = "_$key"; // remember for later because we want this key sorting stuff to have the lowest priority of all sorting
			if ($refkey++) { // don't reverse these! we must always bump refkey if it's to be useful.
				$keytable = $kclass::$table;
				$tablelist[$keytable] = $kclass;
				$q->incorporate($kclass::instanceQfrags($q->joinedInstance($kclass, "_$key", "_{$key}_", array("_$key", '_id')), !$keysToSpecify[$key]));
			}
		}
		// Putting the relative keys before this Qfrags makes the relative sorting take natural precedence

		static::instanceQfrags($q, $args['identifying']); // here we invoke the recursive aspect of the query builder, explicitly requesting this instance's fields and descending down through the include hierarchy pulling in referenced tables

		// We need key-based ordering to have the lowest priority, so we defer adding it until after we've done all the field-based ordering
		if (!$solo) {
			foreach ($keyOrdering as $n) // we can be sure $keyOrdering is an array with at least one value because otherwise $solo would be true
				$q->addSorting($n, true);
			$q->setLimits($args['limit'], $args['start']);
		} else
			$q->setLimits(2);

		if ($args['where'])
			$q->addSQLSelector($args['where']);

		$count = $q->execute(isset($class::$noQueryLogs)); // HERE'S WHERE THE ACTION IS

		if ($solo && $count > 1)
			throw new dbNotSingularX("Where clause '$args[where]' yields more than 1 result for class $class.");

		return $q;
	}

	/**
	* instanceQfrags is the recursive aspect of the loadInstanceData() query builder and is called once for each element in a hierarchy.
	* It's job is to include all the table-level system-defined fields, including the keys in the query result, and then to include each of the fields as well.
	* @return LoadingStatement The accumulated statement
	*/
	private static function instanceQfrags( LoadingStatement $q, $identifying = false, array &$reqrefs = array(), &$inctables = array() )
	{
		$class = get_called_class();

		if (is_array($class::$keys))
			foreach (array_keys($class::$keys) as $key)
				$q->addColumn("_$key");
		else
			$q->addColumn("_{$class::$keys}");

		if (isset($class::$formatSQL)) // assignment intended
			$q->addColumn("_formatted", $class::$formatSQL);

		if (!$identifying) {
			$q->addColumn("_full", 1);
			if (isset($class::$sysvals))
				foreach ($s = (array)$class::$sysvals as $sysval)
					$q->addColumn("_$sysval");

			// Here we process expressly joined-in tables. We don't do this if we're only looking for identifying entries because we assume joined data could not be identifying.
			foreach ($class::getJoins() as $jClass=>$join) {
				if (!is_numeric($jClass) && is_subclass_of($jClass, 'Instance')) {
					if (is_subclass_of($jClass, 'Relation')) {
						$keys = array_keys($jClass::$keys);
						if ($class != $jClass::$keys[$keys[0]]) {
							logWarn("Invalid join specification [$jClass=>$join] ignored: Element class '$class' does not match referent class '{$jClass::$keys[$keys[0]]}'");
							continue; // this is an error condition but we don't treat these kinds of things as fatal in any way.
						}
						$q->joinTable($jClass, array('_id', "_$keys[0]"));
						if (!$join)
							continue;
						if (is_boolean($join)) { // join the relative in all its identifying glory (could be many tables)
							$relClass = $jClass::$keys[$keys[1]];
							$q->incorporate($jClass::instanceQfrags($q->joinedInstance($jClass, "_$keys[1]", "_$keys[1]_", array("_$keys[1]", '_id'), false), true));
							continue;
						}
					} else if (($sig = mb_substr($join, 0, 1)) == '«') { // This is the "referrers" shortcut
						$jField = mb_substr($join, 1);
						$jfDef = $jClass::getFieldDef($jField);
						if (in_array($jfDef['type'], array('belong','require','refer')) && $class::$table == $jfDef['class']::$table) // this is just validation that the join spec is legal
							$q->joinTable($jClass, array('_id', "$jField"));
						else
							logWarn("Invalid join specification [$jClass=>$join] ignored: Referring field $jClass.$jField has class '$jfDef[class]' rather than expected class '$class'");
						continue;
					}
					// instances of all kinds with strings or arrays as the join value end up here
					$q->joinTable($jClass, $join);
				} else
					$q->joinTable(null, $join, null);
			} // looping on join specs
		} // only when we're loading full records

		// Now for the actual defined fields
		foreach ($fds = $class::$fielddefs as $fd) {  // the assignment there prevents array pointer mixup on possible recursion
			if ($identifying && !$fd['identifying'])
				continue; // limit query size when all we care about are the most basic bits of info
			if (!$fd['class'] && !$fd['derived'] || $fd['alias'])
				continue; // some fields might have no database load component... we're skipping those here
			$q->addColumn($fd['name'], $fd['derived']);
			if (!$fd['type'] || $fd['type'] == 'instance') {
				if ($fd['sort'])
					$q->addSorting($fd['name'], $fd['sort'] === 'DESC');
				continue; // Thus endeth processing of all NON reference fields
			}

			// Below we handle only reference fields' recursion & sorting

			if ($fd['type'] == 'include') {
				// No table may be included more than once in the same encapcelated object.
				if (in_array($fd['class']::$table, $inctables))
					throw new ErrorException("Table {$fd['class']::$table} included more than once in the same encapcelation. Check your element field definitions.");
				$inctables[] = $fd['class']::$table;
				$q->incorporate($fd['class']::instanceQfrags($q->joinedInstance($fd['class'], $fd['name'], "$fd[name]_", array($fd['name'], '_id'), $fd['sort']), $identifying, $reqrefs, $inctables), $fd['name']);

			} else if (in_array($fd['type'], array('belong','require','refer','fieldset'))) {
				// No ref type relationship may be traversed twice in a single query because that would imply a recursive loop
				if (in_array($reqref = "{$class::$table}:$fd[name]", $reqrefs))
					logDebug("RECURSION stopped for reference of type '$fd[type]' for field $class.$fd[name]");
				else {
					$reqrefs[] = $reqref;
					$q->incorporate(
						$fd['class']::instanceQfrags(
							$q->joinedInstance($fd['class'], $fd['name'], "$fd[name]_", array($fd['name'], '_id'), $fd['sort']),
							!in_array($fd['type'],array('fieldset','require')), // require and fieldset instances are always loaded fully
							$reqrefs),
						$fd['name']);
				}
			}
		}
		return $q;
	}

	/**
	* This method is responsible for the creation of new Instance objects for the purpose of loading them with data in hand.
	* Override this to undertake cached object management for stored instances.
	* Implementations must use $this->acceptInstanceData() to actually set the values.
	* @param mixed[] $record An associative array in which the column names from the data load are the indexes. They are aliased, so...
	* @param string $loadtime The timestamp for the data load may be provided here.
	* @param string $prefix All the fieldname indexes in the record relevant to your particular instance are prefixed with this string, so when pulling values from the record use $record["$prefix$fn"]
	*/
	protected static function getInstanceFromLoadedData( array $record, $loadtime = null, $prefix = "" )
	{
		$class = get_called_class();
		$obj = new $class;
		if (!$obj->acceptInstanceData($record, $loadtime, $prefix))
			return null;
		return $obj;
	}

	/**
	* This method takes a loaded data record and sets all the values within (Instance)$this to match accordingly.
	* It means "populate this (usually brand new empty) object with the data provided".
	* This includes constructing all referenced Instances duly loaded, and for fieldet and include types, fully recursively.
	* It sets all the keys accordingly, loaded as _$keyname and set as $this->key[_$keyname] or $instance->key.
	* It sets all the defined system values, loaded as _$sysval and set as $this->$sysval.
	* @param mixed[] $record An associative array in which the column names from the data load are the indexes. They are aliased, so...
	* @param string $prefix All the fieldname indexes in the record relevant to your particular instance are prefixed with this string,
	*		so when pulling values from the record use $record["$prefix$fn"]
	* @param string $loadtime The timestamp for the data load may be provided here. If it's not provided we just set $this->loadtime to true.
	* @return mixed Returns the instance key value which is either an integer or an array of integers.
	*/
	final protected function acceptInstanceData( array $record = null, $loadtime = null, $prefix = "" )
	{
		if (!$record)
			return null;
		if (is_array($kdefs = $this::$keys)) // when multiple keys are defined, all must be present
			foreach ($kdefs as $key=>$kClass) {
				if (!($this->key[$key] = 1 * $record["{$prefix}_$key"]))
					throw new ErrorException("Key '$key' for class '$this->_class' came back empty!");
				if ($k++)
					$this->keyed[$key] = $kClass::getInstanceFromLoadedData($record, $loadtime, "{$prefix}_{$key}_");
			}
		else if (!($this->key = 1 * $record["{$prefix}_$kdefs"]))
			return null; // when only 1 key is defined, it's possible for there to be no record, and that's OK

		if (array_key_exists("{$prefix}_full", $record)) // this one can't be handled like the others because it adds its own information not from the record.
			$this->loaded['_loadtime'] = $loadtime ? $loadtime : true;
		if (isset($this::$sysvals))
			foreach ($s = (array)$this::$sysvals as $sysVal)
				if (array_key_exists("{$prefix}_$sysVal", $record) && !isset($this->$sysVal))
					$this->$sysVal = $record["{$prefix}_$sysVal"];

		foreach ($fds = $this::$fielddefs as $fn=>$fd) {
			if (!array_key_exists("$prefix$fn", $record))
				continue;
			$this->loaded[$fn] = $fd['type'] || !$fd['class'] ?
				$record["$prefix$fn"] : // classless and system fields always load raw by design
				$fd['class']::accept_db($record["$prefix$fn"]); // everyone else uses a method

			if ($fd['type'] == 'fieldset') {
				if ($this->included[$fn])  // this case is important because we may be loading with updates already in hand
					$this->included[$fn]->acceptInstanceData($record, $loadtime, "$prefix{$fn}_");
				else // otherwise this load will create a fieldset obj, be it loaded or new
					$this->included[$fn] = $record["$prefix{$fn}__refdef"] ?
						$fd['class']::getInstanceFromLoadedData($record, $loadtime, "$prefix{$fn}_") : $fd['class']::create($this, $fn);
			} else if ($fd['type'] == 'include')
				$this->included[$fn] = $fd['class']::getInstanceFromLoadedData($record, $loadtime, "$prefix{$fn}_");
			else if (in_array($fd['type'], array('require','refer','belong')) && $this->loaded[$fn] && array_key_exists("$prefix{$fn}__id", $record))
				$this->referenced[$fn] = $fd['class']::getInstanceFromLoadedData($record, $loadtime, "$prefix{$fn}_");
		}
		return $this->key;
	}

	public function store( Database $db = null )
	{
		if (!$db)
			if (!($db = $GLOBALS['root']) instanceof Database)
				throw new ErrorException("Must have a database context to store objects.");

		$this::trackChanges("Instance::store(): $this->_handle");
		try {
			$this->storeInstanceData($db);
		} catch (Exception $ex) {
			$this::abortChanges($ex);
		}
		$this::commitChanges();
		return $this->key;
	}

	/**
	* This is the recursive general purpose instance storage method. Because it can make its decisions entirely based on static subclass configuration, it can correctly handle all types.
	* It must be called by a separate method which wraps the call in a transaction because being recursive, this method can't do that without catchng the exception too far inside the loop.
	* At this point we assume that the data is internally consistent. This should usually be enforced by the store() method if not before.
	* If we find inconsistencies at our level, we're throwing an exception.
	* To summarize, store() is called once per encapcelation, while storeInstanceData() is once per atomic instance.
	* @param Database $db The database interface. We'll use global $root if you don't provide one.
	* @return mixed The stored instance's key, even if nothing was written to the database.
	* @throws Exception if there is anything that prevents making sure the instance data is properly represented in the database
	*/
	protected function storeInstanceData( Database $db )
	{
		$this->registerChanges();
		$this->load(); // this should only hit the database if it's not loaded yet
		$values = array();

		// First we need to store the sysvals
		if (isset($this::$sysvals))
			foreach ($s = (array)$this::$sysvals as $sysVal) {
				$raw = $this->$sysVal;
				$values["`_$sysVal`"] = is_numeric($raw) ? $raw : (is_null($raw) ? 'NULL' : "'$raw'");
			}

		// We begin with assembling the columns
		$fds = $this::$fielddefs;
		foreach ($fds as $fn=>$fd) { // the assignment there prevents array pointer mixup on possible recursion

			if ($fd['derived'] || !$fd['class'])
				continue;

			// We can't simply force the storage of all referenced elements because that would require doing store() on them rather than storeInstanceData()
			// With refer types, we make sure that the referenced object is stored and that if storing it results in a new handle for it, it will exist in $this->updated
			// We also ensure that any reference type which requires a value actually has one.
			if (in_array($fd['type'], array('include','require','belong','refer','fieldset'))) {
				if ($fd['type'] == 'fieldset') {
					$obj = $this->included[$fn];
					// Fieldsets are special because regardless of the state of the object, if the scalar ID is null, there should be no fieldset record.
					if (!$this->$fn) {
						if ($fd['required'])
							throw new BadFieldValueX($fd, "Cannot be empty");
						if ($obj->key)
							$obj->delete();
						$this->included[$fn] = $fd['class']::create($this, $fn);
						$id = null;
					} else
						$id = $obj->storeInstanceData($db);
				} else if ($fd['type'] == 'include') {
					$id = $this->included[$fn]->storeInstanceData($db); // this will no-op if there's nothing to update
				} else {
					$id = array_key_exists($fn, $this->updated) ? $this->updated[$fn] :
						(is_object($this->referenced[$fn]) ? $this->referenced[$fn]->key : $this->loaded[$fn]);
					if ($id == '*' || ($fd['type'] != 'refer' && $id < 1))
						throw new BadFieldValueX($fd, "Can't store $this->_handle.$fn because its referent is not yet stored.");
				}
				if ($this->loaded[$fn] != $id)
					$values["`$fn`"] = $id ? $id : 'NULL';
				continue;
			} // reference types

			if (!array_key_exists($fn, $this->updated))
				continue;

			if ($fd['type'] == 'instance')
				$values["`$fn`"] = $this->updated[$fn] ? 'NULL' : "'". $db->dbEscapeString($this->updated[$fn]). "'";

			else { // normal typed fields
				if (is_array($dbval = $fd['class']::put_db($this->updated[$fn], $db)))
					foreach ($dbval as $sub=>$val)
						$values["`{$fn}_$sub`"] = $val;
				else
					$values["`$fn`"] = $dbval;
			}
		} // foreach native field

		// At this point we have stored all included elements and set all fields in the $values array... now we build the query beginning with the keys
		if (is_array($this::$keys)) { // compound referring unique key
			foreach ($keys = $this::$keys as $key=>$kClass) {
				if (!$this->keyed[$key]->_stored || $this->key[$key] < 1)
					$this->key[$key] = $this->keyed[$key]->store();
				$values["`_$key`"] = $this->key[$key];
			}
			$qs = "REPLACE INTO `{$this::$table}` (". implode(',', array_keys($values)) .") VALUES (". implode(',', array_values($values)) .")";

		} else { // non-referring scalar primary key
			if ($this->key) {
				if (!count($values)) {
					logDebug("Nothing to update for $this->_handle");
					return $this->key;
				}
				// Note that REPLACE INTO is NOT appropriate for ELEMENTS because the "DELETE... INSERT" procedure would break foreign keys to this table
				foreach ($values as $name => $value)
					$frags[] = "$name = $value"; // this is just query formatting
				$qs = "UPDATE `{$this::$table}` SET ". implode(',', $frags) ." WHERE _{$this::$keys} = $this->key";
			} else
				$qs = "INSERT INTO `{$this::$table}` (". implode(',', array_keys($values)) .") VALUES (". implode(',', array_values($values)) .")";
		}

		for ($x = 0; $x < 2; $x++) { // only retry once if it fails the first time
			try {
				$db->dbQuery($qs, isset($this::$noQueryLogs) ? '*nolog' : "as ". get_class($this));
				break; // success implies no retry required
			} catch (dbMissingTableX $ex) {
				$class = get_class($this);
				logInfo("A missing table was encountered and we assume it was for $class. Attempting to create table '{$class::$table}'...");
				$ddl = new InstanceDDL($class, $db);
				$db->dbQuery($ddl->getCreateDDL(true)); // for store operations, we don't have a good way to accumulate a table list, so this either works with keys or it fails
			}
		}

		if (!is_array($this::$keys) && $this->key < 1)
			$this->key = $db->dbNewId();

		$this->loaded = []; // because otherwise we'd be stale and merging in won't produce a valid loadtime, et.
		$this->updated = []; // because we are no longer latent and need to act that way.
		$this->referenced = []; // because without values in updated or loaded, none of these can be current.
		return $this->key;
	}

}
