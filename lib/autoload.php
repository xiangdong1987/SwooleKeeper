<?php
/**
 * 自动加载文件
 */
spl_autoload_register(function ($class_name) {
    include __DIR__ . "/" . ucwords($class_name) . '.php';
});