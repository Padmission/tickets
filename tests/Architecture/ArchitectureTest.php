<?php

arch()->preset()->php();

arch()->preset()->security()->ignoring('App\\Console\\DbOpenCommand');
