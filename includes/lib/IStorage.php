<?php

namespace lib;

interface IStorage
{
    function getClient();

    function errmsg();

    function exists($name);

    function get($name);

    function downfile($name, $range = false);

    function upload($name,$tmpfile);

    function savefile($name,$tmpfile);

    function getinfo($name);

    function delete($name);

    function getUploadParam($name, $filename, $max_file_size = 0);

    function getDownUrl($name, $filename, $content_type = null);
}