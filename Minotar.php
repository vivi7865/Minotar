<?php

$config['caching'] = true;

class Minotar {
	const DEFAULT_SKIN = 'char';

	public static $expires = 86400; // 24 hours in seconds


	public static function get($username) {
		$lowercase = strtolower($username);
		
		$skin = './minecraft/skins/'.$lowercase.'.png';
		if(file_exists($skin)) {
			if(time() - filemtime($skin) < self::$expires) return $lowercase;
			$cached = true;
		}

		$binary = self::fetch('http://s3.amazonaws.com/MinecraftSkins/'.$username.'.png');
		if($binary === false) {
			if($cached) return $lowercase;

			header('Status: 404 Not Found');
			return ($username == self::DEFAULT_SKIN)
				? $lowercase
				: self::get(self::DEFAULT_SKIN);
		}

		$img = new Imagick();
		$img->readImageBlob($binary);
		$img->stripImage(); // strip metadata
		$img->writeImage($skin);

		self::_getHelm($img)->writeImage('./minecraft/helms/'.$lowercase.'.png');
		self::_getHead($img)->writeImage('./minecraft/heads/'.$lowercase.'.png');
		self::_getPlayer($img)->writeImage('./minecraft/players/'.$lowercase.'.png');

		return $lowercase;
	}


	protected static function _getHead(Imagick $skin) {
		return $skin->getImageRegion(8, 8, 8, 8);
	}

	protected static function _getHelm(Imagick $skin) {
		$head = self::_getHead($skin);

		// detect helm
		$bg = $skin->exportImagePixels(0, 0, 1, 1, 'RGB', Imagick::PIXEL_CHAR);
		$pixels = $skin->exportImagePixels(40, 8, 8, 8, 'RGB', Imagick::PIXEL_CHAR);
		
		foreach(array_chunk($pixels, 3) as $pixel) {
			if($pixel === $bg) continue;

			// draw helm
			$helm = $skin->getImageRegion(8, 8, 40, 8);
			$head->compositeImage($helm, Imagick::COMPOSITE_DEFAULT, 0, 0);
			break;
		}

		return $head;
	}

	protected static function _getPlayer(Imagick $skin) {
		$player = new Imagick();
		$player->newImage(16, 32, 'transparent', 'png');

		// draw helm
		$player->compositeImage(self::_getHelm($skin), Imagick::COMPOSITE_DEFAULT, 4, 0 );
		// draw body
		$part = $skin->getImageRegion(8, 12, 20, 20);
		$player->compositeImage($part, Imagick::COMPOSITE_DEFAULT, 4, 8);
		// draw arms
		$part = $skin->getImageRegion(4, 12, 44, 20);
		$player->compositeImage($part, Imagick::COMPOSITE_DEFAULT, 0, 8);
		$part->flopImage();
		$player->compositeImage($part, Imagick::COMPOSITE_DEFAULT, 12, 8);
		// draw legs
		$part = $skin->getImageRegion(4, 12, 4, 20);
		$player->compositeImage($part, Imagick::COMPOSITE_DEFAULT, 4, 20);
		$part->flopImage();
		$player->compositeImage($part, Imagick::COMPOSITE_DEFAULT, 8, 20);
		
		return $player;
	}


	private static function fetch($url) {
		$handle = curl_init($url);
		if (false === $handle) {
			return false;
		}
		curl_setopt($handle, CURLOPT_HEADER, false);
		curl_setopt($handle, CURLOPT_FAILONERROR, true);  // this works
		curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15")); // request as if Firefox    
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$contents = curl_exec($handle);
		curl_close($handle);
		return $contents;
	}


	public static function getFilesFromDir($dir) { 
		$files = array(); 
		if ($handle = opendir($dir)) { 
			while (false !== ($file = readdir($handle))) { 
				if ($file[0] != "." && $file != "Thumbs.db") { 
					if(is_dir($dir.'/'.$file)) { 
						$dir2 = $dir.'/'.$file; 
						$files[] = getFilesFromDir($dir2); 
					} 
					else { 
						$files[] = $dir.'/'.$file; 
					} 
				} 
			} 
			closedir($handle); 
		} 
		return self::array_flat($files); 
	} 

	private static function array_flat($array) { 
		foreach($array as $a) { 
			if(is_array($a)) { 
				$tmp = array_merge($tmp, array_flat($a)); 
			} else { 
				$tmp[] = $a; 
			} 
		} 

		return $tmp; 
	} 

}
