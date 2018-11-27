<?php

/**
 * 快速判断两个数据库的不同之处，包括字符集，表，以及表中的字段和属性
 * @author  jiangpengfei12@gmail.com
 * @date    2017-11-09
 */



function error($msg) {
    exit($msg."-----------!!!!error!!!!");
}

class Config {
    public $host;
    public $user;
    public $password;
    public $dbname;
    public $port;

    public function __construct($host, $user, $password, $dbname, $port) {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->dbname = $dbname;
        $this->port = $port;
    }
}

class MysqlDiff {
    private $conn1;
    private $conn2;
    private $table1;
    private $table2;

    public function __construct($config1, $config2) {
        $this->conn1 = mysqli_connect($config1->host, $config1->user, $config1->password, $config1->dbname, $config1->port);
        $this->conn2 = mysqli_connect($config2->host, $config2->user, $config2->password, $config2->dbname, $config2->port);
    }

    /**
     * 检查数据库的字符编码是否相同
     */
    private function diffCharset() {

        $charset1 = array();
        $charset2 = array();

        $sql = "show variables like 'collation_%' ";
        $result = $this->conn1->query($sql);
        while ($row = mysqli_fetch_assoc($result))
        {
            $charset1[$row['Variable_name']] = $row['Value'];
        }

        $result = $this->conn2->query($sql);
        while ($row = mysqli_fetch_assoc($result))
        {
            $charset2[$row['Variable_name']] = $row['Value'];
        }

        foreach($charset1 as $key => $value) {
            if ($value != $charset2[$key]) {
                error("charset $key different \n");
            }
        }
    }

    private function diffTables() {
        $isDiff = false;
        $this->table1 = array();
        $this->table2 = array();

        $sql = "SHOW TABLES";
        $result = $this->conn1->query($sql);
        while ($row = mysqli_fetch_assoc($result))
        {
            foreach($row as $table) {
                array_push($this->table1, $table);
            }
        }

        $result = $this->conn2->query($sql);
        while ($row = mysqli_fetch_assoc($result))
        {
            foreach($row as $table) {
                array_push($this->table2, $table);
            }
        }

        $table1Map = array();
        foreach($this->table1 as $value) {
            $table1Map[$value] = 1;
        }

        foreach($this->table2 as $value) {
            if (!isset($table1Map[$value]) ) {
                // 表不存在
                error("tables name different");
            } else {
                $table1Map[$value] = 2;     //2代表校验过了
            }
        }

        // 从table1Map中找出还没有校验的
        foreach($table1Map as $key=>$value) {
            if ($value == 1) {
                error("db2 lack table $key");
            }
        }
        
        
    }

    // field 检查的属性包括：
    // Filed          字段名
    // Type           字段类型
    // Collation      字符集
    // Null           是否为空
    // Key            
    // Default        默认值
    // Extra          
    // Privileges     权限

    // 不检查的属性有：
    // Comment        备注
    private function diffColumns($tableName) {
        $field1 = array();
        $field2 = array();

        $sql = "SHOW FULL COLUMNS FROM $tableName";
        $result = $this->conn1->query($sql);

        while($row = mysqli_fetch_assoc($result)) {
            $field1[$row['Field']] = $row;
        }

        $sql = "SHOW FULL COLUMNS FROM $tableName";
        $result = $this->conn2->query($sql);

        while($row = mysqli_fetch_assoc($result)) {
            $field2[$row['Field']] = $row;
        }

        //对比field1和field2的区别
        if (count($field1) != count($field2)) {
            //字段数量不一样
            error("``$tableName`` field count different");
        }

        foreach($field1 as $fieldName => $field) {
            if (!isset($field2[$fieldName])) {
                error("``$tableName`` field ``$fieldName`` different");
            }

            foreach($field as $key=>$value) {

                if ($key != "Comment") {
                    if ($value != $field2[$fieldName][$key]) {

                        $tmp = $field2[$fieldName][$key];
                        error("``$tableName`` field ``$fieldName`` property ``$key`` different：$value--$tmp");
                    }
                }
            }
        }
    }

    public function diff() {
        $this->diffCharset();
        $this->diffTables();
        foreach($this->table1 as $tableName) {
            $this->diffColumns($tableName);
        }
    }
}


$config1 = new Config('host','dbuser','password','dbname','port');
$config2 = new Config('host','dbuser','password','dbname','port');

$mysqlDiff = new MysqlDiff($config1, $config2);
$mysqlDiff->diff();
