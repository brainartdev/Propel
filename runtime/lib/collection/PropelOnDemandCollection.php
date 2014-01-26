<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Class for iterating over a statement and returning one Propel object at a time
 *
 * @author     Francois Zaninotto
 * @package    propel.runtime.collection
 */
class PropelOnDemandCollection extends PropelCollection
{
    /**
     * @var       PropelOnDemandIterator
     */
//    protected $iterator;

    /** @var  PDOStatement */
    protected $stmt;
    
    protected $currentRow;

    protected $currentKey;

    protected $isValid;

    protected $formatter;

    protected $enableInstancePoolingOnFinish;

    /**
     * @param PropelFormatter $formatter
     * @param PDOStatement    $stmt
     */
    public function initIterator(PropelFormatter $formatter, PDOStatement $stmt)
    {
        $this->formatter = $formatter;
        $this->stmt = $stmt;
//        $this->dataFetcher = $dataFetcher;
//        $this->iterator = new PropelOnDemandIterator($formatter, $stmt);
        $this->currentKey = -1;
        $this->enableInstancePoolingOnFinish = Propel::disableInstancePooling();
    }

    public function closeCursor()
    {
        $this->stmt->closeCursor();
        if ($this->enableInstancePoolingOnFinish) {
            Propel::enableInstancePooling();
        }
    }

    /**
     * Returns the number of rows in the resultset
     * Warning: this number is inaccurate for most databases. Do not rely on it for a portable application.
     *
     * @return integer Number of results
     */
    public function count()
    {
        return $this->stmt->rowCount();
    }

    // Iterator Interface

    /**
     * Gets the current Model object in the collection
     * This is where the hydration takes place.
     *
     * @see ObjectFormatter::getAllObjectsFromRow()
     *
     * @return BaseObject
     */
    public function current()
    {
        return $this->formatter->getAllObjectsFromRow($this->currentRow);
    }

    /**
     * Gets the current key in the iterator
     *
     * @return string
     */
    public function key()
    {
        return $this->currentKey;
    }

    /**
     * Advances the cursor in the statement
     * Closes the cursor if the end of the statement is reached
     */
    public function next()
    {
        $this->currentRow = $this->stmt->fetch(\PDO::FETCH_NUM);
        $this->currentKey++;
        $this->isValid = (Boolean) $this->currentRow;
        if (!$this->isValid) {
            $this->closeCursor();
        }
    }

    /**
     * Initializes the iterator by advancing to the first position
     * This method can only be called once (this is a NoRewindIterator)
     */
    public function rewind()
    {
        // check that the hydration can begin
        if (null === $this->formatter) {
            throw new PropelException('The On Demand collection requires a formatter. Add it by calling setFormatter()');
        }
//        if (null === $this->dataFetcher) {
//            throw new PropelException('The On Demand collection requires a dataFetcher. Add it by calling setDataFetcher()');
//        }
        if (null !== $this->isValid) {
            throw new PropelException('The On Demand collection can only be iterated once');
        }

        // initialize the current row and key
        $this->next();
    }

    /**
     * @return boolean
     */
    public function valid()
    {
        return (Boolean) $this->isValid;
    }



    /**
     * Get an array representation of the collection
     * Each object is turned into an array and the result is returned
     *
     * @param string $keyColumn If null, the returned array uses an incremental index.
     *                               Otherwise, the array is indexed using the specified column
     * @param boolean $usePrefix If true, the returned array prefixes keys
     *                               with the model class name ('Article_0', 'Article_1', etc).
     * @param string $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME,
     *                               TableMap::TYPE_STUDLYPHPNAME, TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME,
     *                               TableMap::TYPE_NUM. Defaults to TableMap::TYPE_PHPNAME.
     * @param boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param array   $alreadyDumpedObjects   List of objects to skip to avoid recursion
     *
     * <code>
     * $bookCollection->toArray();
     * array(
     *  0 => array('Id' => 123, 'Title' => 'War And Peace'),
     *  1 => array('Id' => 456, 'Title' => 'Don Juan'),
     * )
     * $bookCollection->toArray('Id');
     * array(
     *  123 => array('Id' => 123, 'Title' => 'War And Peace'),
     *  456 => array('Id' => 456, 'Title' => 'Don Juan'),
     * )
     * $bookCollection->toArray(null, true);
     * array(
     *  'Book_0' => array('Id' => 123, 'Title' => 'War And Peace'),
     *  'Book_1' => array('Id' => 456, 'Title' => 'Don Juan'),
     * )
     * </code>
     *
     * @return array
     */
    public function toArray($keyColumn = null, $usePrefix = false, $keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array())
    {
        $ret = array();
        $keyGetterMethod = 'get' . $keyColumn;

        /** @var $obj BaseObject */
        foreach ($this as $key => $obj) {
            $key = null === $keyColumn ? $key : $obj->$keyGetterMethod();
            $key = $usePrefix ? ($this->getModel() . '_' . $key) : $key;
            $ret[$key] = $obj->toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, true);
        }

        return $ret;
    }

    /**
     * Populates the collection from an array
     * Each object is populated from an array and the result is stored
     * Does not empty the collection before adding the data from the array
     *
     * @param array $arr
     */
    public function fromArray($arr)
    {
        throw new PropelException('The On Demand Collection is read only');
    }
    
    // ArrayAccess Interface
    
    /**
     * @throws PropelException
     *
     * @param integer $offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        if ($offset === $this->currentKey) {
            return true;
        }

        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($offset === $this->currentKey) {
            return $this->currentRow;
        }

        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     * @param mixed   $value
     */
    public function offsetSet($offset, $value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     */
    public function offsetUnset($offset)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    // Serializable Interface

    /**
     * @throws PropelException
     */
    public function serialize()
    {
        throw new PropelException('The On Demand Collection cannot be serialized');
    }

    /**
     * @throws PropelException
     *
     * @param string $data
     *
     * @return void
     */
    public function unserialize($data)
    {
        throw new PropelException('The On Demand Collection cannot be serialized');
    }

    // ArrayObject methods

    public function append($value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function prepend($value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function asort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function exchangeArray($input)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function getArrayCopy()
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    public function getFlags()
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    public function ksort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function natcasesort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function natsort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function setFlags($flags)
    {
        throw new PropelException('The On Demand Collection does not allow acces by offset');
    }

    public function uasort($cmp_function)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    public function uksort($cmp_function)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * {@inheritdoc}
     */
    public function exportTo($parser, $usePrefix = true, $includeLazyLoadColumns = true)
    {
        throw new PropelException('A PropelOnDemandCollection cannot be exported.');
    }
}
