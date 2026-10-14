<?php

/*
| Fallback front controller for shared hosting where the whole package sits in the web root instead of the "public" folder.
| The root .htaccess normally routes requests into public/ itself; this file covers servers that ignore .htaccess.
| Prefer pointing the domain's document root at the "public" folder.
*/
require __DIR__.'/public/index.php';
