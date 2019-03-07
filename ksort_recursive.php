<?php

function ksort_recursive(&$array) {
  if (is_array($array)) {
    ksort($array);
    array_walk($array, 'ksort_recursive');
  }
}
