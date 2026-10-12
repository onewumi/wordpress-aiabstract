<?php
// AIAbstract 缓存目录保护文件：防止 Web 直接列出/下载缓存数据
// 缓存与日志数据请通过插件管理页访问，不要直接访问本目录文件。
if (!defined('ABSPATH')) {
    exit;
}