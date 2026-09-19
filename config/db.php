<?php
// =====================================================================
// config/db.php — Universal Database Bridge (Oracle 10g + SQLite Fallback)
// Sports Tournament Management System (STMS)
// American International University-Bangladesh (AIUB)
// Course: Advanced Database Management System (ADBMS) — Final Term
// =====================================================================

define('DB_USER', 'SCOTT');
define('DB_PASS', 'tiger');
define('DB_DSN',  '//localhost:1521/XE');

if (!defined('OCI_COMMIT_ON_SUCCESS')) define('OCI_COMMIT_ON_SUCCESS', 32);
if (!defined('OCI_ASSOC'))             define('OCI_ASSOC', 1);
if (!defined('OCI_NUM'))               define('OCI_NUM', 2);
if (!defined('OCI_BOTH'))              define('OCI_BOTH', 3);

$GLOBALS['__stms_last_error'] = null;

if (!class_exists('STMS_Statement')) {
    class STMS_Statement {
        public $stmt;
        public $sql;
        public $binds = [];
        public $pdo;

        public function __construct($pdo, $sql) {
            $this->pdo   = $pdo;
            $this->sql   = $sql;
            $this->binds = [];
        }
    }
}

/**
 * Returns database connection (Oracle XE if available, else SQLite PDO)
 */
function getOracleConnection() {
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }

    // 1. Try Oracle OCI8 if extension is loaded
    if (extension_loaded('oci8') && function_exists('oci_connect')) {
        $oracleConn = @oci_connect(DB_USER, DB_PASS, DB_DSN, 'UTF8');
        if ($oracleConn) {
            $conn = $oracleConn;
            return $conn;
        }
    }

    // 2. Seamless local SQLite fallback
    $dbFile = __DIR__ . '/../database/stms.db';
    if (!file_exists($dbFile)) {
        require_once __DIR__ . '/../database/init_sqlite.php';
    }

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

        // Custom SQLite functions for Oracle compatibility
        $pdo->sqliteCreateFunction('SYSDATE', function() {
            return date('Y-m-d H:i:s');
        });
        $pdo->sqliteCreateFunction('NVL', function($val, $default) {
            return ($val === null || $val === '') ? $default : $val;
        }, 2);
        $pdo->sqliteCreateFunction('TO_DATE', function($val, $fmt = null) {
            return $val;
        });
        $pdo->sqliteCreateFunction('TO_CHAR', function($val, $fmt = null) {
            return (string)$val;
        });

        $conn = $pdo;
        return $conn;
    } catch (Exception $e) {
        die('<div style="font-family:sans-serif;color:red;padding:20px;background:#fff0f0;border:1px solid red;border-radius:8px;">'
          . '<h3>Database Connection Error</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>');
    }
}

/**
 * Synchronize DML changes directly into Oracle 10g XE database
 */
function syncToOracleXE($sql, $binds = []) {
    static $hasSqlPlus = null;
    $sqlplus = 'C:\\oraclexe\\app\\oracle\\product\\10.2.0\\server\\bin\\sqlplus.exe';
    if ($hasSqlPlus === null) {
        $hasSqlPlus = file_exists($sqlplus);
    }
    if (!$hasSqlPlus) return;

    $oracleSql = $sql;
    // Replace bind params with properly escaped literals for Oracle SQL
    foreach ($binds as $key => $val) {
        $cleanKey = ltrim($key, ':');
        if ($val === null || $val === '') {
            $rep = 'NULL';
        } elseif (is_numeric($val)) {
            $rep = $val;
        } elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $rep = "TO_DATE('" . $val . "', 'YYYY-MM-DD')";
        } else {
            $rep = "'" . str_replace("'", "''", (string)$val) . "'";
        }
        $oracleSql = preg_replace('/:' . preg_quote($cleanKey, '/') . '\b/i', $rep, $oracleSql);
    }

    // Automatically convert any bare 'YYYY-MM-DD' date literal for Oracle
    $oracleSql = preg_replace("/(?<!TO_DATE\()'(20\d{2}-\d{2}-\d{2})'/i", "TO_DATE('$1', 'YYYY-MM-DD')", $oracleSql);

    $oracleSql = trim($oracleSql);
    if (!empty($oracleSql)) {
        $connStr = 'SCOTT/tiger@127.0.0.1:1521/XE';
        $script = "SET HEADING OFF;\nSET FEEDBACK OFF;\nSET ECHO OFF;\n" . rtrim($oracleSql, ';') . ";\nCOMMIT;\nexit;\n";
        
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $process = @proc_open("\"$sqlplus\" -S \"$connStr\"", $descriptorspec, $pipes);
        if (is_resource($process)) {
            @fwrite($pipes[0], $script);
            @fclose($pipes[0]);
            @fclose($pipes[1]);
            @fclose($pipes[2]);
            @proc_close($process);
        }
    }
}

// ─── OCI8 Polyfill when OCI8 extension is not loaded ─────────────────
if (!function_exists('oci_connect')) {

    function oci_connect($username, $password, $connection_string = null, $character_set = 'UTF8') {
        return getOracleConnection();
    }

    function oci_pconnect($username, $password, $connection_string = null, $character_set = 'UTF8') {
        return getOracleConnection();
    }

    function oci_parse($connection, $sql) {
        if ($connection instanceof PDO) {
            return new STMS_Statement($connection, $sql);
        }
        return false;
    }

    function oci_bind_by_name($statement, $bv_name, &$variable, $maxlength = -1, $type = 0) {
        if ($statement instanceof STMS_Statement) {
            $key = ltrim($bv_name, ':');
            $statement->binds[$key] =& $variable;
            return true;
        }
        return false;
    }

    function oci_execute($statement, $mode = 0) {
        if ($statement instanceof STMS_Statement) {
            $sql = $statement->sql;
            // Clean up Oracle-specific syntax for SQLite
            $sql = preg_replace('/FETCH\s+FIRST\s+(\d+)\s+ROWS\s+ONLY/i', 'LIMIT $1', $sql);
            $sql = preg_replace('/seq_\w+\.NEXTVAL/i', 'NULL', $sql);

            try {
                $stmt = $statement->pdo->prepare($sql);
                foreach ($statement->binds as $key => &$val) {
                    $stmt->bindValue(':' . $key, $val);
                }
                $res = $stmt->execute();
                $statement->stmt = $stmt;
                if ($res && preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $statement->sql)) {
                    syncToOracleXE($statement->sql, $statement->binds);
                }
                return true;
            } catch (PDOException $e) {
                $GLOBALS['__stms_last_error'] = [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                    'offset'  => 0,
                    'sqltext' => $sql
                ];
                error_log('STMS Polyfill DB Error: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    function oci_fetch_assoc($statement) {
        if ($statement instanceof STMS_Statement && $statement->stmt) {
            $row = $statement->stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) return false;
            $upperRow = [];
            foreach ($row as $k => $v) {
                $upperRow[strtoupper($k)] = $v;
            }
            return $upperRow;
        }
        return false;
    }

    function oci_fetch_array($statement, $mode = 0) {
        if ($statement instanceof STMS_Statement && $statement->stmt) {
            $row = $statement->stmt->fetch(PDO::FETCH_BOTH);
            if ($row === false) return false;
            $upperRow = [];
            foreach ($row as $k => $v) {
                $upperKey = is_string($k) ? strtoupper($k) : $k;
                $upperRow[$upperKey] = $v;
            }
            return $upperRow;
        }
        return false;
    }

    function oci_num_rows($statement) {
        if ($statement instanceof STMS_Statement && $statement->stmt) {
            return $statement->stmt->rowCount();
        }
        return 0;
    }

    function oci_free_statement($statement) {
        if ($statement instanceof STMS_Statement) {
            $statement->stmt = null;
        }
        return true;
    }

    function oci_close($connection) {
        return true;
    }

    function oci_error($resource = null) {
        return $GLOBALS['__stms_last_error'] ?? [
            'message' => 'No error',
            'code'    => 0,
            'offset'  => 0,
            'sqltext' => ''
        ];
    }
}

/**
 * Translates Oracle-specific SQL constructs to SQLite dialect for local fallback.
 */
function cleanSqlForSqlite($sql) {
    // 1. OFFSET X ROWS FETCH NEXT Y ROWS ONLY -> LIMIT Y OFFSET X
    $sql = preg_replace('/OFFSET\s+(\d+)\s+ROWS\s+FETCH\s+NEXT\s+(\d+)\s+ROWS\s+ONLY/i', 'LIMIT $2 OFFSET $1', $sql);

    // 2. FETCH FIRST X ROWS? ONLY -> LIMIT X
    $sql = preg_replace('/FETCH\s+FIRST\s+(\d+)\s+ROWS?\s+ONLY/i', 'LIMIT $1', $sql);

    // 3. seq_xxx.NEXTVAL -> NULL
    $sql = preg_replace('/seq_\w+\.NEXTVAL/i', 'NULL', $sql);

    // 4. EXTRACT(YEAR FROM SYSDATE) / EXTRACT(YEAR FROM col)
    $sql = preg_replace('/EXTRACT\s*\(\s*YEAR\s+FROM\s+SYSDATE\s*\)/i', "CAST(STRFTIME('%Y', 'now') AS INT)", $sql);
    $sql = preg_replace('/EXTRACT\s*\(\s*YEAR\s+FROM\s+([a-zA-Z0-9_\.]+)\s*\)/i', "CAST(STRFTIME('%Y', $1) AS INT)", $sql);

    // 5. TRUNC(SYSDATE) -> DATE('now')
    $sql = preg_replace('/TRUNC\s*\(\s*SYSDATE\s*\)/i', "DATE('now')", $sql);

    // 6. Bare SYSDATE keyword -> DATE('now')
    $sql = preg_replace('/\bSYSDATE\b/i', "DATE('now')", $sql);

    return $sql;
}

/**
 * Run a SELECT query and return all rows as associative array.
 */
function oracleQuery($conn, $sql, $binds = []) {
    if ($conn instanceof PDO) {
        $cleanSql = cleanSqlForSqlite($sql);
        try {
            $stmt = $conn->prepare($cleanSql);
            foreach ($binds as $key => $value) {
                $stmt->bindValue(':' . ltrim($key, ':'), $value);
            }
            $stmt->execute();
            $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $upper = [];
                foreach ($row as $k => $v) {
                    $upper[strtoupper($k)] = $v;
                }
                $rows[] = $upper;
            }
            return $rows;
        } catch (PDOException $e) {
            error_log('oracleQuery error: ' . $e->getMessage() . ' in ' . $sql);
            return [];
        }
    }

    $stmt = oci_parse($conn, $sql);
    foreach ($binds as $key => &$value) {
        oci_bind_by_name($stmt, ':' . ltrim($key, ':'), $value);
    }
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }
    oci_free_statement($stmt);
    return $rows;
}

/**
 * Run a DML (INSERT / UPDATE / DELETE) with bind variables.
 */
function oracleExecute($conn, $sql, $binds = []) {
    if ($conn instanceof PDO) {
        $cleanSql = cleanSqlForSqlite($sql);
        try {
            $stmt = $conn->prepare($cleanSql);
            foreach ($binds as $key => $value) {
                $stmt->bindValue(':' . ltrim($key, ':'), $value);
            }
            $res = $stmt->execute();
            if ($res) {
                syncToOracleXE($sql, $binds);
            }
            return $res;
        } catch (PDOException $e) {
            error_log('oracleExecute error: ' . $e->getMessage());
            return false;
        }
    }

    $stmt = oci_parse($conn, $sql);
    foreach ($binds as $key => &$value) {
        oci_bind_by_name($stmt, ':' . ltrim($key, ':'), $value);
    }
    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    oci_free_statement($stmt);
    return $result;
}

