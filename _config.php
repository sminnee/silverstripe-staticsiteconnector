<?php

// Curl can get stuck on big files for while
ini_set('max_execution_time',240);		// 4m
ini_set('gd.jpeg_ignore_warning', 1);	// framework's GD complains about the state of some 3rd party JPEG's. This supresses those errors.
