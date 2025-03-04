<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTableData
 * @package dokuwiki\plugin\struct\meta
 *
 * This class is for accessing the data stored for a page in a schema
 *
 */
class AccessTablePage extends AccessTable
{
    public const DEFAULT_PAGE_RID = 0;

    public function __construct($schema, $pid, $ts = 0, $rid = 0)
    {
        $ts = $ts ?: time();
        parent::__construct($schema, $pid, $ts, $rid);
    }

    /**
     * adds an empty data set for this schema and page
     *
     * This is basically a delete for the schema fields of a page
     *
     * @return bool
     */
    public function clearData()
    {
        $data = array();

        foreach ($this->schema->getColumns() as $col) {
            if ($col->isMulti()) {
                $data[$col->getLabel()] = array();
            } else {
                $data[$col->getLabel()] = null;
            }
        }

        return $this->saveData($data);
    }

    /**
     * @return int|bool
     */
    protected function getLastRevisionTimestamp()
    {
        $table = 'data_' . $this->schema->getTable();
        $where = "WHERE pid = ?";
        $opts = [$this->pid];
        if ($this->ts) {
            $where .= " AND REV > 0 AND rev <= ?";
            $opts[] = $this->ts;
        }

        /** @noinspection SqlResolve */
        $sql = "SELECT rev FROM $table $where ORDER BY rev DESC LIMIT 1";
        $ret = $this->sqlite->queryValue($sql, $opts);
        // make sure we don't cast empty result to 0 (serial data has rev = 0)
        if ($ret !== false) $ret = (int)$ret;
        return $ret;
    }

    /**
     * @inheritDoc
     */
    protected function validateTypeData($data)
    {
        if ($this->ts == 0) {
            throw new StructException("Saving with zero timestamp does not work.");
        }
        return true;
    }

    /**
     * Remove latest status from previous page data
     */
    protected function beforeSave()
    {
        /** @noinspection SqlResolve */
        $ok = $this->sqlite->query(
            "UPDATE $this->stable SET latest = 0 WHERE latest = 1 AND pid = ? AND rid = 0",
            [$this->pid]
        );
        /** @noinspection SqlResolve */
        return $ok && $this->sqlite->query(
            "UPDATE $this->mtable SET latest = 0 WHERE latest = 1 AND pid = ? AND rid = 0",
            [$this->pid]
        );
    }

    /**
     * Names of non-input columns to be inserted into SQL query.
     * Field 'published' is skipped because only plugins use it and
     * we don't want to interfere with the default NULL value
     */
    protected function getSingleNoninputCols()
    {
        return ['rid, pid, rev, latest'];
    }

    /**
     * @inheritDoc
     */
    protected function getSingleNoninputValues()
    {
        return [self::DEFAULT_PAGE_RID, $this->pid, $this->ts, 1];
    }

    /**
     * @inheritDoc
     */
    protected function getMultiSql()
    {
        /** @noinspection SqlResolve */
        return "INSERT INTO $this->mtable (latest, rev, pid, rid, colref, row, value) VALUES (?,?,?,?,?,?,?)";
    }

    /**
     * @inheritDoc
     */
    protected function getMultiNoninputValues()
    {
        return [AccessTable::DEFAULT_LATEST, $this->ts, $this->pid, self::DEFAULT_PAGE_RID];
    }
}
