<?php
require 'rb.php';
define("DB_HOST", "localhost"); //Сервер базы данных
define("DB_USERNAME", "user"); //Пользователь базы данных
define("DB_PASSWORD", "password"); //Пароль от пользователя базы данных
define("DB_NAME", "vezdekod"); //Имя базы данных
R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USERNAME, DB_PASSWORD);
