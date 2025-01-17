<?php
namespace Kafoso\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Kafoso\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdInterbaseKeywords;

class FirebirdInterbasePlatform extends AbstractPlatform
{
    private $FBVERSION_DEFAULTVAL = "UNKNOWN VERSION";
    private $fbversion = null;
      
    /*
    public function SetEntityManager($em) {
        $this->entityManager = $em;
    }
    */
    
    public function SetFBVersion($version) {
         exec("echo Konstruktor: $version > /tmp/fb6");
        $this->fbversion = $version;
    }
    
    
    
    public function GetFirebirdVersion() {
        if($this->entityManager != null) {
            exec("echo entityManager ist nicht null > /tmp/fbv2");
            
            $connection = $this->entityManager->getConnection();
            $sql = $this->getODSVersionSql();
            $resultSetFirebirdVersion = $connection->query($sql);
            $wert = $resultSetFirebirdVersion->fetchColumn();
            //$this->_firebirdVersion = $this->_platform->getFirebirdVersionFromODSVersion($wert);
            
            return $this->getFirebirdVersionFromODSVersion($wert);
        }
        else {
            exec("echo entityManager ist auch null > /tmp/fbv2");
            return null;
        }
    }
    
    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName()
    {
        return "FirebirdInterbase";
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIdentifierLength()
    {
        return 31;
    }

    /**
     * Returns the max length of constraint names
     *
     * @return integer
     */
    public function getMaxConstraintIdentifierLength()
    {
        return 27;
    }

    /**
     * Checks if the identifier exceeds the platform limit
     *
     * @param \Doctrine\DBAL\Schema\Identifier|string   $aIdentifier    The identifier to check
     * @param integer                                   $maxLength      Length limit to check. Usually the result of
     *                                                                  {@link getMaxIdentifierLength()} should be passed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function checkIdentifierLength($aIdentifier, $maxLength)
    {
        $maxLength || $maxLength = $this->getMaxIdentifierLength();
        $name = ($aIdentifier instanceof \Doctrine\DBAL\Schema\AbstractAsset) ?
                $aIdentifier->getName() : $aIdentifier;

        if (strlen($name) > $this->getMaxIdentifierLength()) {
            throw \Doctrine\DBAL\DBALException::notSupported
                    ('Identifier ' . $name . ' is too long for firebird platform. Maximum identifier length is ' . $this->getMaxIdentifierLength());
        }
    }

    /**
     * Generates an internal ID based on the table name and a suffix
     * @param array|string|\Doctrine\DBAL\Schema\Identifier $prefix     Name, Identifier object or array of names or
     *                                                                  identifier objects to use as prefix.
     * @param integer                                       $maxLength  Length limit to check. Usually the result of
     *                                                                  {@link getMaxIdentifierLength()} should be passed
     *
     * @return \Doctrine\DBAL\Schema\Identifier
     */
    protected function generateIdentifier($prefix, $suffix, $maxLength)
    {
        $needQuote = false;
        $fullId = '';
        $shortId = '';
        is_array($prefix) || $prefix = [$prefix];
        $ml = floor(($maxLength - strlen($suffix)) / count($prefix));
        foreach ($prefix as $p) {
            if (!$p instanceof \Doctrine\DBAL\Schema\AbstractAsset)
                $p = new \Doctrine\DBAL\Schema\Identifier($p);
            $fullId .= $p->getName() . '_';
            if (strlen($p->getName()) >= $ml) {
                $c = crc32($p->getName());
                $shortId .= substr_replace($p->getName(), sprintf("X%04x", $c & 0xFFFF), $ml - 5) . '_';
            } else {
                $shortId .= $p->getName() . '_';
            }
            $needQuote = $needQuote | $p->isQuoted();
        }
        $fullId .= $suffix;
        $shortId .= $suffix;
        if (strlen($fullId) > $maxLength) {
            return new \Doctrine\DBAL\Schema\Identifier($needQuote ? $this->quoteIdentifier($shortId) : $shortId);
        } else {
            return new \Doctrine\DBAL\Schema\Identifier($needQuote ? $this->quoteIdentifier($fullId) : $fullId);
        }
    }

    /**
     * Quotes a SQL-Statement
     *
     * @param string $statement
     * @return string
     */
    protected function quoteSql($statement)
    {
        return $this->quoteStringLiteral($statement);
    }

    /**
     * Returns a primary key constraint name for the table
     *
     * The format is tablename_PK. If the combined name exceeds the length limit, the table name gets shortened.
     *
     * @param \Doctrine\DBAL\Schema\Identifier|string   $aTable Table name or identifier
     *
     * @return string
     */
    protected function generatePrimaryKeyConstraintName($aTable)
    {
        return $this->generateIdentifier([$aTable], 'PK', $this->getMaxConstraintIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getRegexpExpression()
    {
        return 'SIMILAR TO';
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'POSITION (' . $substr . ' in ' . $str . ')';
        }
        return 'POSITION (' . $substr . ', ' . $str . ', ' . $startPos . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return 'DATEADD(' . $days . ' DAY TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BIN_AND (' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return 'BIN_OR (' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return 'DATEADD(-' . $days . ' DAY TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return 'DATEADD(' . $months . ' MONTH TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return 'DATEADD(-' . $months . ' MONTH TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        if (self::DATE_INTERVAL_UNIT_QUARTER === $unit) {
            // Firebird does not support QUARTER - convert to month
            $interval *= 3;
            $unit = self::DATE_INTERVAL_UNIT_MONTH;
        }
        if ($operator == '-') {
            $interval *= -1;
        }
        return 'DATEADD(' . $unit . ', ' . $interval . ', ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    public function supportsForeignKeyConstraints()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function usesSequenceEmulatedIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceName($tableName, $columnName)
    {
        return $this->generateIdentifier([$tableName], 'D2IS', $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceTriggerName($tableName, $columnName)
    {
        return $this->generateIdentifier([$tableName], 'D2IT', $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsViews()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSchemas()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsIdentityColumns()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Firebird does not allow to create databases via SQL
     */
    public function supportsCreateDropDatabase()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Signals that the firebird driver supports limited rows
     *
     * The SQL is build in doModifyLimitQuery
     *
     * @return boolean
     */
    public function supportsLimitOffset()
    {
        return TRUE;
    }

    /**
     * Whether the platform prefers sequences for ID generation.
     *
     * Firebird/Interbase do not have autoinc-fields, thus sequences need to
     * be used for sequence generation.
     *
     * @return boolean
     */
    public function prefersSequences()
    {
        return true;
    }

    public function prefersIdentityColumns()
    {
        return false;
    }

    /**
     * Adds a "Limit" using the firebird ROWS m TO n syntax
     *
     * @param string $query
     * @param integer $limit limit to numbers of records
     * @param integer $offset starting point
     *
     * @return string
     */
    protected function doModifyLimitQuery($query, $limit, $offset)
    {
        if ($limit === NULL && $offset === NULL)
            return $query; // No limitation specified - change nothing

        if ($offset === NULL) {
            // A limit is specified, but no offset, so the syntax ROWS <n> is used
            return $query . ' ROWS ' . (int) $limit;
        }
        $from = (int) $offset + 1; // Firebird starts the offset at 1
        if ($limit === NULL) {
            $to = '9000000000000000000'; // should be beyond a reasonable  number of rows
        } else {
            $to = $from + $limit - 1;
        }
        return $query . ' ROWS ' . $from . ' TO ' . $to;
    }

    public function getListTablesSQL()
    {
        return 'SELECT TRIM(RDB$RELATION_NAME) AS RDB$RELATION_NAME
                FROM RDB$RELATIONS
                WHERE
                    (RDB$SYSTEM_FLAG=0 OR RDB$SYSTEM_FLAG IS NULL) and
        			(RDB$VIEW_BLR IS NULL)';
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return 'SELECT
                    TRIM(RDB$RELATION_NAME) AS RDB$RELATION_NAME,
                    TRIM(RDB$VIEW_SOURCE) AS RDB$VIEW_SOURCE
                FROM RDB$RELATIONS
                WHERE
                    (RDB$SYSTEM_FLAG=0 OR RDB$SYSTEM_FLAG IS NULL) and
                    (RDB$RELATION_TYPE = 1)';
    }

    /**
     * Generates simple sql expressions usually used in metadata-queries
     *
     * @param array $expressions
     * @return string
     */
    protected function makeSimpleMetadataSelectExpression(array $expressions)
    {
        $result = '(';
        $i = 0;
        foreach ($expressions as $f => $v) {
            if ($i > 0) {
                $result .= ' AND ';
            }
            if (($v instanceof \Doctrine\DBAL\Schema\AbstractAsset) ||
                    (is_string($v))) {
                $result .= 'UPPER(' . $f . ') = UPPER(\'' . $this->unquotedIdentifierName($v) . '\')';
            } else {
                if ($v === null) {
                    $result .= $f . ' IS NULL';
                } else {
                    $result .= $f . ' = ' . $v;
                }
            }
            $i++;
        }
        $result .= ')';
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1 FROM RDB$DATABASE';
    }

    /**
     * {@inheritDoc}
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getDropDatabaseSQL($database)
    {
        throw \Doctrine\DBAL\DBALException::notSupported(__METHOD__);
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * Combines multiple statements into an execute block statement
     *
     * @param array|string $sql
     * @return string
     */
    protected function getExecuteBlockSql(array $params = array())
    {
        $params = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params
        );

        if ($params['formatLineBreak']) {
            $break = "\n";
            $indent = '  ';
        } else {
            $break = ' ';
            $indent = '';
        }
        $result = 'EXECUTE BLOCK ';
        if (!empty($params['blockParams'])) {
            $result .= '(';
            $n = 0;
            foreach ($params['blockParams'] as $paramName => $paramDelcaration) {
                if ($n > 0)
                    $result .= ', ';
                $result .= $paramName . ' ' . $paramDelcaration;
                $n++;
            }
            $result .= ') ' . $break;
        }
        $result .= 'AS' . $break;
        if (is_array($params['blockVars'])) {
            foreach ($params['blockVars'] as $variableName => $variableDeclaration) {
                $result .= $indent . 'DECLARE ' . $variableName . ' ' . $variableDeclaration . '; ' . $break;
            }
        }
        $result .= "BEGIN" . $break;
        foreach ((array) $params['statements'] as $stm) {
            $result .= $indent . $stm . $break;
        }
        $result .= "END" . $break;
        return $result;
    }

    /**
     * Builds an Execute Block statement with a bunch of Execute Statement calls
     *
     * @param array|string $sql Statement(s) to execute.
     * @param array $params
     * @param array $variableDeclarations
     * @return string
     */
    protected function getExecuteBlockWithExecuteStatementsSql(array $params = array())
    {
        $params = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params
        );
        $statements = [];
        foreach ((array) $params['statements'] as $s) {
            $statements[] = $this->getExecuteStatementPSql($s) . ';';
        }
        $params['statements'] = $statements;
        return $this->getExecuteBlockSql($params);
    }

    /**
     * Generates a PSQL-Statement to drop all views of a table
     *
     * Note: This statement needs a variable TMP_VIEW_NAME VARCHAR(255) declared
     *
     * @param string $tableNameVarName Variable used in the stored procedure or block to identify the related table name
     * @return string
     */
    public function getDropAllViewsOfTablePSqlSnippet($table, $inBlock = false)
    {
        $result = 'FOR SELECT TRIM(v.RDB$VIEW_NAME) ' .
                'FROM RDB$VIEW_RELATIONS v, RDB$RELATIONS r ' .
                'WHERE ' .
                'TRIM(UPPER(v.RDB$RELATION_NAME)) = TRIM(UPPER(' . $this->quoteStringLiteral($this->unquotedIdentifierName($table)) . ')) AND ' .
                'v.RDB$RELATION_NAME = r.RDB$RELATION_NAME AND ' .
                '(r.RDB$SYSTEM_FLAG IS NULL or r.RDB$SYSTEM_FLAG = 0) AND ' .
                '(r.RDB$RELATION_TYPE = 0) INTO :TMP_VIEW_NAME DO BEGIN ' .
                'EXECUTE STATEMENT \'DROP VIEW "\'||:TMP_VIEW_NAME||\'"\'; END';

        if ($inBlock) {
            $result = $this->getExecuteBlockSql(
                [
                    'statements' => $result,
                    'formatLineBreak' => false,
                    'blockVars' => [
                        'TMP_VIEW_NAME' => 'varchar(255)',
                    ]
                ]
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterSequenceSQL(\Doctrine\DBAL\Schema\Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
                ' RESTART WITH ' . ($sequence->getInitialValue() - 1);
    }

    /**
     * Generates a execute statement PSQL-Statement
     *
     * @param string $aStatement
     * @return string
     */
    protected function getExecuteStatementPSql($aStatement)
    {
        return 'EXECUTE STATEMENT ' . $this->quoteSql($aStatement);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPlainDropSequenceSQL($sequence)
    {
        return 'DROP SEQUENCE ' . $this->getQuotedNameOf($sequence);
    }

    /**
     * Returns a simple DROP TRIGGER statement
     *
     * @param string $aTrigger
     * @return string
     */
    protected function getDropTriggerSql($aTrigger)
    {
        return 'DROP TRIGGER ' . $this->getQuotedNameOf($aTrigger);
    }

    protected function getDropTriggerIfExistsPSql($aTrigger, $inBlock = false)
    {
        $result = sprintf(
            'IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE %s)) THEN BEGIN %s; END',
            $this->makeSimpleMetadataSelectExpression([
                'RDB$TRIGGER_NAME' => $aTrigger,
                'RDB$SYSTEM_FLAG' => 0,
            ]),
            $this->getExecuteStatementPSql($this->getDropTriggerSql($aTrigger))
        );
        if ($inBlock) {
            return $this->getExecuteBlockSql([
                'statements' => $result,
                'formatLineBreak' => false,
            ]);
        }
        return $result;
    }

    protected function getCombinedSqlStatements($sql, $aSeparator)
    {
        if (is_array($sql)) {
            $result = '';
            foreach ($sql as $stm) {
                $result .= is_array($stm) ? $this->getCombinedSqlStatements($stm) : $stm . $aSeparator;
            }
            return $result;
        }
        return $sql . $aSeparator;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof \Doctrine\DBAL\Schema\Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        if (stripos($sequence, '_D2IS')) {
            // Seems to be a autoinc-sequence. Try to drop trigger before
            $triggerName = str_replace('_D2IS', '_D2IT', $sequence);
            return $this->getExecuteBlockWithExecuteStatementsSql([
                'statements' => [
                    $this->getDropTriggerIfExistsPSql($triggerName, true),
                    $this->getPlainDropSequenceSQL($sequence)
                ],
            ]);
        }
        return $this->getPlainDropSequenceSQL($sequence);
    }

    /**
     * {@inheritDoc}
     *
     * Foreign keys are identified via constraint names in firebird
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    /**
     * Returns just the function used to get the next value of a sequence
     *
     * @param string $sequenceName
     * @return string
     */
    public function getSequenceNextValFunctionSQL($sequenceName)
    {
        return 'NEXT VALUE FOR ' . $sequenceName;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sequenceName
     * @return string
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT ' . $this->getSequenceNextValFunctionSQL($sequenceName) . ' FROM RDB$DATABASE';
    }
    
    /**
     * Creates the sql-command to determine the ods-version
     * 
     * @return string
     */
    public function getODSVersionSql() {
        return "SELECT MON\$ODS_MAJOR FROM MON\$DATABASE";
    }
    
    /** 
     * Determines the firebird-version based on the given ods-version
     * 
     * @param type $version
     * @return string|real
     */
    public function getFirebirdVersionFromODSVersion($version) {
        
        
        switch("$version") {
            case "10.0": return 1.0; break; // break nach return ist defensiv programmiert
            case "10.1": return 1.5; break;
            case "11.0": return 2.0; break;
            case "11.1": return 2.1; break;
            case "11.2": return 2.5; break;
            case "12.0": return 3.0; break;
            case "13.0": return 4.0; break;
            default: return $this->FBVERSION_DEFAULTVAL;
        }
        
        return $defaultVal; // Return am ende trotz default in switch aufgrund defensiver programmierung
    }
    
    

    /**
     * {@inheritDoc}
     *
     * It's not possible to set a default isolation level or change the isolation level of of
     * a running transaction on Firebird, because the SET TRANSACTION command starts a new
     * transaction
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return parent::getSetTransactionIsolationSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INTEGER';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT';
    }

    /**
     * {@inheritDoc}
     *
     * NOTE: This statement also tries to drop related views and the trigger used to simulate autoinc-fields
     *
     * @param string $table
     */
    public function getDropTableSQL($table)
    {
        $dropTriggerIfExistsPSql = $this->getDropTriggerIfExistsPSql($this->getIdentitySequenceTriggerName($table, null), true);
        $dropRelatedViewsPSql = $this->getDropAllViewsOfTablePSqlSnippet($table, true);
        $dropTableSql = parent::getDropTableSQL($table);
        return $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                $dropTriggerIfExistsPSql,
                $dropRelatedViewsPSql,
                $dropTableSql,
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'DELETE FROM ' . $tableName;
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = [
            'boolean' => 'boolean',
            'tinyint' => 'smallint',
            'smallint' => 'smallint',
            'mediumint' => 'integer',
            'int' => 'integer',
            'integer' => 'integer',
            'serial' => 'integer',
            'int64' => 'bigint',
            'long' => 'bigint',
            'char' => 'string',
            'text' => 'string', // Yes, really. 'char' is internally called text.
            'varchar' => 'string',
            'varying' => 'string',
            'longvarchar' => 'string',
            'cstring' => 'string',
            'date' => 'date',
            'timestamp' => 'datetime',
            'time' => 'time',
            'float' => 'float',
            'double' => 'float',
            'real' => 'float',
            'decimal' => 'decimal',
            'numeric' => 'decimal',
            'blob' => 'blob',
            'binary' => 'blob',
            'blob sub_type text' => 'text',
            'blob sub_type binary' => 'blob',
            'short' => 'smallint',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * {@inheritDoc}
     *
     * Taken from the PostgreSql-Driver and adapted for Firebird
     */
    public function getAlterTableSQL(TableDiff $diff, $entityManager = null)
    {
        $sql = [];
        $commentsSQL = [];
        $columnSql = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;

            $comment = $this->getColumnComment($column);

            if (null !== $comment && '' !== $comment) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                        $diff->getName($this)->getQuotedName($this), $column->getQuotedName($this), $comment
                );
            }
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = 'DROP ' . $column->getQuotedName($this);
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        foreach ($diff->changedColumns as $columnDiff) {
            /** @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff */
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = $columnDiff->getOldColumnName()->getQuotedName($this);
            $column = $columnDiff->column;

            if ($columnDiff->hasChanged('type') || $columnDiff->hasChanged('precision') || $columnDiff->hasChanged('scale') || $columnDiff->hasChanged('fixed')) {
                $type = $column->getType();

                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $type->getSqlDeclaration($column->toArray(), $this);
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('default') || $columnDiff->hasChanged('type')) {
                $defaultClause = null === $column->getDefault() ? ' DROP DEFAULT' : ' SET' . $this->getDefaultValueDeclarationSQL($column->toArray());
                $query = 'ALTER ' . $oldColumnName . $defaultClause;
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            
            if ($columnDiff->hasChanged('notnull')) {
                $newNullFlag_original = $column->getNotnull() ? 1 : 'NULL';
                $firebirdVersion = $this->fbversion;
                $dbgStr = $firebirdVersion == null ? "Ist null" : $firebirdVersion;
                exec("echo " . $dbgStr  . " > /tmp/fbv");
                
                if($firebirdVersion != null && $firebirdVersion != "" && $firebirdVersion < 3) {
                    $newNullFlag = $newNullFlag_original;
                $sql[] = 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = ' .
                        $newNullFlag . ' ' .
                        'WHERE UPPER(RDB$FIELD_NAME) = ' .
                        'UPPER(\'' . $columnDiff->getOldColumnName()->getName() . '\') AND ' .
                        'UPPER(RDB$RELATION_NAME) = UPPER(\'' . $diff->getName($this)->getName() . '\')';
                }
                else { // Use Syntax for Firebird 3+    
                    //$null_or_notnull = $column->getNotnull() ? " NOT NULL" : "NULL";   
                    $null_or_notnull = $column->getNotnull() ? " SET NOT NULL" : " DROP NOT NULL";   
                    $tabellenname = $diff->getName($this)->getName();
                    $feldname = $columnDiff->getOldColumnName()->getName();
                    //$sqlCMD =  "ALTER TABLE $tabellenname ALTER $feldname SET $null_or_notnull;";
                    $sqlCMD =  "ALTER TABLE $tabellenname ALTER $feldname $null_or_notnull;";
                    $sql[] = $sqlCMD; 
                }
            }

            if ($columnDiff->hasChanged('autoincrement')) {
                if ($column->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $this->getIdentitySequenceName($diff->name, $oldColumnName);

                    $sql[] = "CREATE SEQUENCE " . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ") FROM " . $diff->getName($this)->getQuotedName($this) . "))";
                    $query = "ALTER " . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                    $sql[] = "ALTER TABLE " . $diff->getName($this)->getQuotedName($this) . " " . $query;
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = "ALTER " . $oldColumnName . " " . "DROP DEFAULT";
                    $sql[] = "ALTER TABLE " . $diff->getName($this)->getQuotedName($this) . " " . $query;
                }
            }

            if ($columnDiff->hasChanged('comment')) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                        $diff->getName($this)->getQuotedName($this), $column->getQuotedName($this), $this->getColumnComment($column)
                );
            }

            if ($columnDiff->hasChanged('length')) {
                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $column->getType()->getSqlDeclaration($column->toArray(), $this);
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new \Doctrine\DBAL\Schema\Identifier($oldColumnName);

            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) .
                    ' ALTER COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = [];

        if (!$this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            if ($diff->newName !== false) {
                throw \Doctrine\DBAL\DBALException::notSupported(__METHOD__ . ' Cannot rename tables because firebird does not support it');
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff), $sql, $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     *
     * Actually Firebird can store up to 32K bytes in a varchar, but we assume UTF8, thus the limit is 8190
     */
    public function getVarcharMaxLength()
    {
        return 8190;
    }

    /**
     * {@inheritDoc}
     *
     * Varchars character set binary are used for small blob/binary fields.
     */
    public function getBinaryMaxLength()
    {
        return 8190;
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return FirebirdInterbaseKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */

    /**
     * If it fits into a varchar, a varchar is used.
     *
     * @param array $field
     *
     * @return string
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        if (!empty($field['length']) && is_numeric($field['length']) &&
                $field['length'] <= $this->getVarcharMaxLength()) {
            return 'VARCHAR(' . $field['length'] . ')';
        }

        return 'BLOB SUB_TYPE TEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIME';
    }

    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        if ($fixed) {
            if ($length) {
                return 'CHAR(' . $length . ')';
            }
            return 'CHAR(255)';
        }
        if ($length) {
            return 'VARCHAR(' . $length . ')';
        }
        return 'VARCHAR(255)';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset   name of the charset
     *
     * @return string  DBMS specific SQL code portion needed to set the CHARACTER SET
     *                 of a field declaration.
     */
    public function getColumnCharsetDeclarationSQL($charset)
    {
        if (!empty($charset)) {
            return ' CHARACTER SET ' . $charset;
        }
        return '';
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        if ($length > $this->getBinaryMaxLength()) {
            return 'BLOB';
        }
        if ($fixed) {
            if ($length) {
                return 'CHAR(' . $length . ')';
            }
            return 'CHAR(' . $this->getBinaryDefaultLength() . ')';
        }
        if ($length) {
            return 'VARCHAR(' . $length . ')';
        }
        return 'VARCHAR(' . $this->getBinaryDefaultLength() . ')';
    }

    /**
     * {@inheritDoc}
     * @param string $name
     * @param array $field
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['type']) && strtolower($field['type']) == 'binary') {
            $field['charset'] = 'binary';
            $field['collation'] = 'octets';
        }
        return parent::getColumnDeclarationSQL($name, $field);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE GLOBAL TEMPORARY TABLE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableSQL()
    {
        return 'GLOBAL TEMPORARY';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $this->checkIdentifierLength($tableName, $this->getMaxIdentifierLength());

        $isTemporary = (isset($options['temporary']) && !empty($options['temporary']));

        $indexes = isset($options['indexes']) ? $options['indexes'] : [];
        $options['indexes'] = [];


        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && !empty($options['primary'])) {
            $columnListSql .= ', CONSTRAINT ' . $this->generatePrimaryKeyConstraintName($tableName) . ' PRIMARY KEY (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        $query = 'CREATE ' .
                ($isTemporary ? $this->getTemporaryTableSQL() . ' ' : '') .
                'TABLE ' . $tableName;

        $query .= ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        if ($isTemporary) {
            $query .= ' ON COMMIT PRESERVE ROWS';   // Session level temporary tables
        }

        $sql[] = $query;

        // Create sequences and a trigger for autoinc-fields if necessary

        foreach ($columns as $name => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence'], 1);
            }

            if (isset($column['autoincrement']) && $column['autoincrement'] ||
                    (isset($column['autoinc']) && $column['autoinc'])) {
                $sql = array_merge($sql, $this->getCreateAutoincrementSql($name, $tableName));
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        if (isset($indexes) && !empty($indexes)) {
            foreach ($indexes as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        return $sql;
    }

    public function getCreateAutoincrementSql($column, $tableName)
    {
        $sql = [];

        if (!$column instanceof \Doctrine\DBAL\Schema\AbstractAsset)
            $column = new \Doctrine\DBAL\Schema\Identifier($column);

        $tableName = new \Doctrine\DBAL\Schema\Identifier($tableName);
        $sequenceName = $this->getIdentitySequenceName($tableName, $column);
        $triggerName = $this->getIdentitySequenceTriggerName($tableName, $column);
        $sequence = new \Doctrine\DBAL\Schema\Sequence($sequenceName, 1, 1);

        $sql[] = $this->getCreateSequenceSQL($sequence);

        $sql[] = 'CREATE TRIGGER ' . $triggerName . ' FOR ' . $tableName->getQuotedName($this) . '
            BEFORE INSERT
            AS
            BEGIN
                IF ((NEW.' . $column->getQuotedName($this) . ' IS NULL) OR
                   (NEW.' . $column->getQuotedName($this) . ' = 0)) THEN
                BEGIN
                    NEW.' . $column->getQuotedName($this) . ' = NEXT VALUE FOR ' . $sequence->getQuotedName($this) . ';
                END
            END;';

        return $sql;
    }

    /**
     * Returns the SQL statements to drop the autoincrement for the given table name.
     *
     * @param string $table The table name to drop the autoincrement for.
     *
     * @return array
     */
    public function getDropAutoincrementSql($table)
    {
        $table = $this->normalizeIdentifier($table);
        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($table);
        $identitySequenceName = $this->getIdentitySequenceName(
            $table->isQuoted() ? $table->getQuotedName($this) : $table->getName(),
            ''
        );
        $sql = [];
        $sql[] = 'DROP TRIGGER ' . $autoincrementIdentifierName;
        $sql[] = $this->getDropSequenceSQL($identitySequenceName);
        $sql[] = $this->getDropConstraintSQL($autoincrementIdentifierName, $table->getQuotedName($this));
        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getListSequencesSQL($database)
    {
        return 'select trim(rdb$generator_name) as rdb$generator_name from rdb$generators where rdb$system_flag is distinct from 1';
    }

    /**
     * {@inheritDoc}
     *
     * Returns a query resulting cointaining the following data:
     *
     * FIELD_NAME: Field Name
     * FIELD_DOMAIN: Domain
     * FIELD_TYPE: Internal Id of the field type
     * FIELD_TYPE_NAME: Name of the field type
     * FIELD_SUB_TYPE: Internal Id of the field sub-type
     * FIELD_LENGTH: Length of the field in *byte*
     * FIELD_CHAR_LENGTH: Length of the field in *chracters*
     * FIELD_PRECISION: Precision
     * FIELD_SCALE: Scale
     * FIELD_DEFAULT_SOURCE: Default declaration including the DEFAULT keyword and quotes if any
     *
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        $query = <<<'___query___'
            SELECT TRIM(r.RDB$FIELD_NAME) AS "FIELD_NAME",
            TRIM(f.RDB$FIELD_NAME) AS "FIELD_DOMAIN",
            TRIM(f.RDB$FIELD_TYPE) AS "FIELD_TYPE",
            TRIM(typ.RDB$TYPE_NAME) AS "FIELD_TYPE_NAME",
            f.RDB$FIELD_SUB_TYPE AS "FIELD_SUB_TYPE",
            f.RDB$FIELD_LENGTH AS "FIELD_LENGTH",
            f.RDB$CHARACTER_LENGTH AS "FIELD_CHAR_LENGTH",
            f.RDB$FIELD_PRECISION AS "FIELD_PRECISION",
            f.RDB$FIELD_SCALE AS "FIELD_SCALE",
            MIN(TRIM(rc.RDB$CONSTRAINT_TYPE)) AS "FIELD_CONSTRAINT_TYPE",
            MIN(TRIM(i.RDB$INDEX_NAME)) AS "FIELD_INDEX_NAME",
            r.RDB$NULL_FLAG as "FIELD_NOT_NULL_FLAG",
            r.RDB$DEFAULT_SOURCE AS "FIELD_DEFAULT_SOURCE",
            r.RDB$FIELD_POSITION AS "FIELD_POSITION",
            r.RDB$DESCRIPTION AS "FIELD_DESCRIPTION",
            f.RDB$CHARACTER_SET_ID as "CHARACTER_SET_ID",
            TRIM(cs.RDB$CHARACTER_SET_NAME) as "CHARACTER_SET_NAME",
            f.RDB$COLLATION_ID as "COLLATION_ID",
            TRIM(cl.RDB$COLLATION_NAME) as "COLLATION_NAME"
            FROM RDB$RELATION_FIELDS r
            LEFT OUTER JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
            LEFT OUTER JOIN RDB$INDEX_SEGMENTS s ON s.RDB$FIELD_NAME=r.RDB$FIELD_NAME
            LEFT OUTER JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME AND i.RDB$RELATION_NAME = r.RDB$RELATION_NAME
            LEFT OUTER JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME AND rc.RDB$INDEX_NAME = i.RDB$INDEX_NAME AND rc.RDB$RELATION_NAME = i.RDB$RELATION_NAME
            LEFT OUTER JOIN RDB$REF_CONSTRAINTS REFC ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
            LEFT OUTER JOIN RDB$TYPES typ ON typ.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' AND typ.RDB$TYPE = f.RDB$FIELD_TYPE
            LEFT OUTER JOIN RDB$TYPES sub ON sub.RDB$FIELD_NAME = 'RDB$FIELD_SUB_TYPE' AND sub.RDB$TYPE = f.RDB$FIELD_SUB_TYPE
            LEFT OUTER JOIN RDB$CHARACTER_SETS cs ON cs.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID
            LEFT OUTER JOIN RDB$COLLATIONS cl ON cl.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID AND cl.RDB$COLLATION_ID = f.RDB$COLLATION_ID
            WHERE UPPER(r.RDB$RELATION_NAME) = UPPER(':TABLE')
            GROUP BY "FIELD_NAME", "FIELD_DOMAIN", "FIELD_TYPE", "FIELD_TYPE_NAME", "FIELD_SUB_TYPE",  "FIELD_LENGTH",
                     "FIELD_CHAR_LENGTH", "FIELD_PRECISION", "FIELD_SCALE", "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE",
                     "FIELD_POSITION",
                     "CHARACTER_SET_ID",
                     "CHARACTER_SET_NAME",
                     "COLLATION_ID",
                     "COLLATION_NAME",
                     "FIELD_DESCRIPTION"
            ORDER BY "FIELD_POSITION"
___query___;
        return str_replace(':TABLE', $table, $query);
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        $query = <<<'___query___'
      SELECT TRIM(rc.RDB$CONSTRAINT_NAME) AS constraint_name,
      TRIM(i.RDB$RELATION_NAME) AS table_name,
      TRIM(s.RDB$FIELD_NAME) AS field_name,
      TRIM(i.RDB$DESCRIPTION) AS description,
      TRIM(rc.RDB$DEFERRABLE) AS is_deferrable,
      TRIM(rc.RDB$INITIALLY_DEFERRED) AS is_deferred,
      TRIM(refc.RDB$UPDATE_RULE) AS on_update,
      TRIM(refc.RDB$DELETE_RULE) AS on_delete,
      TRIM(refc.RDB$MATCH_OPTION) AS match_type,
      TRIM(i2.RDB$RELATION_NAME) AS references_table,
      TRIM(s2.RDB$FIELD_NAME) AS references_field,
      (s.RDB$FIELD_POSITION + 1) AS field_position
      FROM RDB$INDEX_SEGMENTS s
      LEFT JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME
      LEFT JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME
      LEFT JOIN RDB$REF_CONSTRAINTS refc ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
      LEFT JOIN RDB$RELATION_CONSTRAINTS rc2 ON rc2.RDB$CONSTRAINT_NAME = refc.RDB$CONST_NAME_UQ
      LEFT JOIN RDB$INDICES i2 ON i2.RDB$INDEX_NAME = rc2.RDB$INDEX_NAME
      LEFT JOIN RDB$INDEX_SEGMENTS s2 ON i2.RDB$INDEX_NAME = s2.RDB$INDEX_NAME AND s.RDB$FIELD_POSITION = s2.RDB$FIELD_POSITION
      WHERE rc.RDB$CONSTRAINT_TYPE = 'FOREIGN KEY' and UPPER(i.RDB$RELATION_NAME) = UPPER(':TABLE')
      ORDER BY rc.RDB$CONSTRAINT_NAME, s.RDB$FIELD_POSITION
___query___;

        return str_replace(':TABLE', $table, $query);
    }

    /**
     * {@inheritDoc}
     *
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        $query = <<<'___query___'
      SELECT
        TRIM(RDB$INDEX_SEGMENTS.RDB$FIELD_NAME) AS field_name,
        TRIM(RDB$INDICES.RDB$DESCRIPTION) AS description,
        TRIM(RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_NAME)  as constraint_name,
        TRIM(RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_TYPE) as constraint_type,
        TRIM(RDB$INDICES.RDB$INDEX_NAME) as index_name,
        RDB$INDICES.RDB$UNIQUE_FLAG as unique_flag,
        RDB$INDICES.RDB$INDEX_TYPE as index_type,
        (RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION + 1) AS field_position,
        RDB$INDICES.RDB$INDEX_INACTIVE as index_inactive,
        TRIM(RDB$INDICES.RDB$FOREIGN_KEY) as foreign_key
     FROM RDB$INDEX_SEGMENTS
     LEFT JOIN RDB$INDICES ON RDB$INDICES.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
     LEFT JOIN RDB$RELATION_CONSTRAINTS ON RDB$RELATION_CONSTRAINTS.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
     WHERE UPPER(RDB$INDICES.RDB$RELATION_NAME) = UPPER(':TABLE')
     ORDER BY RDB$INDICES.RDB$INDEX_NAME, RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_NAME, RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION
___query___;
        return str_replace(':TABLE', $table, $query);
    }

    /**
     * {@inheritDoc}
     *
     * Firebird return column names in upper case by default
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper($column);
    }

    private function unquotedIdentifierName($name)
    {
        $name instanceof \Doctrine\DBAL\Schema\AbstractAsset || $name = new \Doctrine\DBAL\Schema\Identifier($name);
        return $name->getName();
    }

    /**
     * Returns a quoted name if necessar
     *
     * @param string|\Doctrine\DBAL\Schema\Identifier $name
     * @return string
     */
    protected function getQuotedNameOf($name)
    {
        if ($name instanceof \Doctrine\DBAL\Schema\AbstractAsset) {
            return $name->getQuotedName($this);
        }
        $id = new \Doctrine\DBAL\Schema\Identifier($name);
        return $id->getQuotedName($this);
    }

    /**
     * Normalize the identifier
     *
     * Firebird converts identifiers to uppercase if not quoted. This function converts the identifier to uppercase
     * if it is *not* quoted *and* does not not contain any Uppercase characters. Otherwise the function
     * quotes the identifier.
     *
     * @param string $name Identifier
     *
     * @return Identifier The normalized identifier.
     */
    private function normalizeIdentifier($name)
    {
        if ($name instanceof \Doctrine\DBAL\Schema\AbstractAsset) {
            $result = new \Doctrine\DBAL\Schema\Identifier($name->getQuotedName($this));
        } else {
            $result = new \Doctrine\DBAL\Schema\Identifier($name);
        }
        if ($result->isQuoted()) {
            return $result;
        } else {
            if (strtolower($result->getName() == $result->getName())) {
                return new \Doctrine\DBAL\Schema\Identifier(strtoupper($result->getName()));
            } else {
                return new \Doctrine\DBAL\Schema\Identifier($this->quoteIdentifier($result->getName()));
            }
        }
    }

    private function getAutoincrementIdentifierName(Identifier $table)
    {
        $identifierName = $table->getName() . '_AI_PK';
        return $table->isQuoted()
            ? $this->quoteSingleIdentifier($identifierName)
            : $identifierName;
    }
}
