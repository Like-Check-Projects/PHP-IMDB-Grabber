<?php
/**
* IMDB PHP Parser
*
* This class can be used to retrieve data from IMDB.com with PHP. This script will fail once in
* a while, when IMDB changes *anything* on their HTML. Guys, it's time to provide an API!
*
* @link http://fabian-beiner.de
* @copyright 2010 Fabian Beiner
* @author Fabian Beiner (mail [AT] fabian-beiner [DOT] de)
* @license MIT License
*
* @version 4.0.1 (February 1st, 2010)
*
*/

class IMDB {
	private $_sSource = null;
	private $_sUrl    = null;
	private $_sId     = null;
	public  $_bFound  = false;

	const IMDB_CAST         = '#<a href="/name/(\w+)/" onclick="\(new Image\(\)\)\.src=\'/rg/castlist/position-(\d|\d\d)/images/b\.gif\?link=/name/(\w+)/\';">(.*)</a>#Ui';
	const IMDB_COUNTRY      = '#<a href="/Sections/Countries/(\w+)/">#Ui';
	const IMDB_DIRECTOR     = '#<a href="/name/(\w+)/" onclick="\(new Image\(\)\)\.src=\'/rg/directorlist/position-(\d|\d\d)/images/b.gif\?link=name/(\w+)/\';">(.*)</a><br/>#Ui';
	const IMDB_GENRE        = '#<a href="/Sections/Genres/(\w+|\w+\-\w+)/">(\w+|\w+\-\w+)</a>#Ui';
	const IMDB_MPAA         = '#<h5><a href="/mpaa">MPAA</a>:</h5>\s*<div class="info-content">\s*(.*)\s*</div>#Ui';
	const IMDB_PLOT         = '#<h5>Plot:</h5>\s*<div class="info-content">\s*(.*)\s*<a#Ui';
	const IMDB_POSTER       = '#<a name="poster" href="(.*)" title="(.*)"><img border="0" alt="(.*)" title="(.*)" src="(.*)" /></a>#Ui';
	const IMDB_RATING       = '#<b>(\d\.\d/10)</b>#Ui';
	const IMDB_RELEASE_DATE = '#<h5>Release Date:</h5>\s*\s*<div class="info-content">\s*(.*) \((.*)\)#Ui';
	const IMDB_RUNTIME      = '#<h5>Runtime:</h5>\s*<div class="info-content">\s*(.*)\s*</div>#Ui';
	const IMDB_SEARCH       = '#<b>Media from&nbsp;<a href="/title/tt(\d+)/"#i';
	const IMDB_TAGLINE      = '#<h5>Tagline:</h5>\s*<div class="info-content">\s*(.*)\s*</div>#Ui';
	const IMDB_TITLE        = '#<title>(.*) \((.*)\)</title>#Ui';
	const IMDB_URL          = '#http://(.*\.|.*)imdb.com/(t|T)itle(\?|/)(..\d+)#i';
	const IMDB_VOTES        = '#&nbsp;&nbsp;<a href="ratings" class="tn15more">(.*) votes</a>#Ui';
	const IMDB_WRITER       = '#<a href="/name/(\w+)/" onclick="\(new Image\(\)\)\.src=\'/rg/writerlist/position-(\d|\d\d)/images/b\.gif\?link=name/(\w+)/\';">(.*)</a> \((.*)\)<br/>#Ui';

	/**
	 * Public constructor.
	 *
	 * @param string $sSearch
	 */
	public function __construct($sSearch) {
		$sUrl = $this->findUrl($sSearch);
		if ($sUrl) {
			$bFetch        = $this->fetchUrl($this->_sUrl);
			$this->_bFound = true;
		}
	}

	/**
	 * Little REGEX helper.
	 *
	 * @param string $sRegex
	 * @param string $sContent
	 * @param int    $iIndex;
	 */
	private function getMatch($sRegex, $sContent, $iIndex = 1) {
		preg_match($sRegex, $sContent, $aMatches);
		if ($iIndex > count($aMatches)) return;
		if ($iIndex == null) {
			return $aMatches;
		}
		return $aMatches[(int)$iIndex];
	}

	/**
	 * Little REGEX helper.
	 *
	 * @param string $sRegex
	 * @param string $sContent
	 * @param int    $iIndex;
	 */
	private function getMatches($sRegex, $iIndex = null) {
		preg_match_all($sRegex, $this->_sSource, $aMatches);
		if ((int)$iIndex) return $aMatches[$iIndex];
		return $aMatches;
	}

	/**
	 * Save an image.
	 *
	 * @param string $sUrl
	 */
	private function saveImage($sUrl) {
		$sUrl   = trim($sUrl);
		$bolDir = false;
		if (!is_dir(getcwd() . '/posters')) {
			if (mkdir(getcwd() . '/posters', 0777)) {
				$bolDir = true;
			}
		}
		$sFilename = getcwd() . '/posters/' . ereg_replace("[^0-9]", "", basename($this->_sUrl)) . '.jpg';
		if (file_exists($sFilename)) {
			return 'posters/' . basename($sFilename);
		}
		if (is_dir(getcwd() . '/posters') OR $bolDir) {
			if (function_exists('curl_init')) {

				$oCurl = curl_init($sUrl);
				curl_setopt_array($oCurl, array (
												CURLOPT_VERBOSE => 0,
												CURLOPT_HEADER => 0,
												CURLOPT_RETURNTRANSFER => 1,
												CURLOPT_TIMEOUT => 5,
												CURLOPT_CONNECTTIMEOUT => 5,
												CURLOPT_REFERER => $sUrl,
												CURLOPT_BINARYTRANSFER => 1));
				$sOutput = curl_exec($oCurl);
				curl_close($oCurl);
				$oFile = fopen($sFilename, 'x');
				fwrite($oFile, $sOutput);
				fclose($oFile);
				return 'posters/' . basename($sFilename);
			} else {
				$oImg = imagecreatefromjpeg($sUrl);
				imagejpeg($oImg, $sFilename);
				return 'posters/' . basename($sFilename);
			}
			return false;
		}
		return false;
	}

	/**
	 * Find a valid Url out of the passed argument.
	 *
	 * @param string $sSearch
	 */
	private function findUrl($sSearch) {
		$sSearch = trim($sSearch);
		if ($aUrl = $this->getMatch(self::IMDB_URL, $sSearch, 4)) {
			$this->_sId  = 'tt' . ereg_replace('[^0-9]', '', $aUrl);
			$this->_sUrl = 'http://www.imdb.com/title/' . $this->_sId .'/';
			return true;
		} else {
			$sTemp    = 'http://www.imdb.com/find?s=all&q=' . str_replace(' ', '+', $sSearch) . '&x=0&y=0';
			$bFetch   = $this->fetchUrl($sTemp);
			if ($bFetch) {
				if ($strMatch = $this->getMatch(self::IMDB_SEARCH, $this->_sSource)) {
					$this->_sUrl = 'http://www.imdb.com/title/tt' . $strMatch . '/';
					unset($this->_sSource);
					return true;
				}
			}
		}
		return false;
	}

	/**
	* Fetch data from given Url.
	* Uses cURL if installed, otherwise falls back to file_get_contents.
	*
	* @param string $sUrl
	* @param int    $iTimeout;
	*/
	private function fetchUrl($sUrl, $iTimeout = 15) {
		$sUrl = trim($sUrl);
		if (function_exists('curl_init')) {
			$oCurl = curl_init($sUrl);
			curl_setopt_array($oCurl, array (
											CURLOPT_VERBOSE => 0,
											CURLOPT_HEADER => 0,
											CURLOPT_FRESH_CONNECT => true,
											CURLOPT_RETURNTRANSFER => 1,
											CURLOPT_TIMEOUT => (int)$iTimeout,
											CURLOPT_CONNECTTIMEOUT => (int)$iTimeout,
											CURLOPT_REFERER => $sUrl));
			$sOutput = curl_exec($oCurl);

			if ($sOutput === false) {
				return false;
			}

			$aInfo = curl_getinfo($oCurl);
			if ($aInfo['http_code'] != 200) {
				return false;
			}
			$this->_sSource = str_replace("\n", '', (string)$sOutput);
			curl_close($oCurl);
			return true;
		} else {
			$sOutput = @file_get_contents($sUrl, 0);
			if (strpos($http_response_header[0], '200') === false){
				return false;
			}
			$this->_sSource = str_replace("\n", '', (string)$sOutput);
			return true;
		}
		return false;
	}

	/**
	 * Returns the cast.
	 */
	public function getCast($iOutput = null, $bMore = true) {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_CAST, 4);
			if (is_array($sReturned)) {
				if ($iOutput) {
					foreach ($sReturned as $i => $sName) {
						if ($i >= $iOutput) break;
						$sReturn[] = $sName;
					}
					return implode(' / ', $sReturn) . (($bMore) ? '&hellip;' : '');
				}
				return implode(' / ', $sReturned);
			}
			return $sReturned;
		}
		return 'n/A';
	}

	/**
	 * Returns the cast as links.
	 */
	public function getCastAsUrl($iOutput = null, $bMore = true) {
		if ($this->_sSource) {
			$sReturned1 = $this->getMatches(self::IMDB_CAST, 4);
			$sReturned2 = $this->getMatches(self::IMDB_CAST, 3);
			if (is_array($sReturned1)) {
				if ($iOutput) {
					foreach ($sReturned1 as $i => $sName) {
						if ($i >= $iOutput) break;
						$aReturn[] = '<a href="http://www.imdb.com/name/' . $sReturned2[$i] . '/">' . $sName . '</a>';;
					}
					return implode(' / ', $aReturn) . (($bMore) ? '&hellip;' : '');
				}
				return implode(' / ', $sReturned);
			}
			return '<a href="http://www.imdb.com/name/' . $sReturned2 . '/">' . $sReturned1 . '</a>';;
		}
		return 'n/A';
	}

	/**
	 * Returns the countr(y|ies).
	 */
	public function getCountry() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_COUNTRY, 1);
			if (is_array($sReturned)) {
				return implode(' / ', $sReturned);
			}
			return $sReturned;
		}
		return 'n/A';
	}

	/**
	 * Returns the countr(y|ies) as link(s).
	 */
	public function getCountryAsUrl() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_COUNTRY, 1);
			if (is_array($sReturned)) {
				foreach ($sReturned as $sCountry) {
					$aReturn[] = '<a href="http://www.imdb.com/Sections/Countries/' . $sCountry . '/">' . $sCountry . '</a>';
				}
				return implode(' / ', $aReturn);
			}
			return '<a href="http://www.imdb.com/Sections/Countries/' . $sReturned . '/">' . $sReturned . '</a>';
		}
		return 'n/A';
	}

	/**
	 * Returns the director(s).
	 */
	public function getDirector() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_DIRECTOR, 4);
			if (is_array($sReturned)) {
				return implode(' / ', $sReturned);
			}
			return $sReturned;
		}
		return 'n/A';
	}

	/**
	 * Returns the director(s) as link(s).
	 */
	public function getDirectorAsUrl() {
		if ($this->_sSource) {
			$sReturned1 = $this->getMatches(self::IMDB_DIRECTOR, 4);
			$sReturned2 = $this->getMatches(self::IMDB_DIRECTOR, 1);
			if (is_array($sReturned1)) {
				foreach ($sReturned1 as $i => $sDirector) {
					$aReturn[] = '<a href="http://www.imdb.com/name/' . $sReturned2[$i] . '/">' . $sDirector . '</a>';
				}
				return implode(' / ', $aReturn);
			}
			return '<a href="http://www.imdb.com/name/' . $sReturned2 . '/">' . $sReturned1 . '</a>';
		}
		return 'n/A';
	}

	/**
	 * Returns the genre(s).
	 */
	public function getGenre() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_GENRE, 1);
			if (is_array($sReturned)) {
				return implode(' / ', $sReturned);
			}
			return $sReturned;
		}
		return 'n/A';
	}

	/**
	 * Returns the genre(s) as link(s).
	 */
	public function getGenreAsUrl() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_GENRE, 1);
			if (is_array($sReturned)) {
				foreach ($sReturned as $i => $sGenre) {
					$aReturn[] = '<a href="http://www.imdb.com/Sections/Genres/' . $sGenre . '/">' . $sGenre . '</a>';
				}
				return implode(' / ', $aReturn);
			}
			return '<a href="http://www.imdb.com/Sections/Genres/' . $sReturned . '/">' . $sReturned . '</a>';
		}
		return 'n/A';
	}

	/**
	 * Returns the mpaa.
	 */
	public function getMpaa() {
		if ($this->_sSource) {
			return implode('' , $this->getMatches(self::IMDB_MPAA, 1));
		}
		return 'n/A';
	}

	/**
	 * Returns the plot.
	 */
	public function getPlot() {
		if ($this->_sSource) {
			return implode('' , $this->getMatches(self::IMDB_PLOT, 1));
		}
		return 'n/A';
	}

	/**
	 * Download the poster, cache it and return the local path to the image.
	 */
	public function getPoster() {
		if ($this->_sSource) {
			if ($sPoster = $this->saveImage(implode("", $this->getMatches(self::IMDB_POSTER, 5)), 'poster.jpg')) {
				return $sPoster;
			}
			return implode('', $this->getMatches(self::IMDB_POSTER, 5));
		}
		return 'n/A';
	}

	/**
	 * Returns the rating.
	 */
	public function getRating() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_RATING, 1));
		}
		return 'n/A';
	}

	/**
	 * Returns the release date.
	 */
	public function getReleaseDate() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_RELEASE_DATE, 1));
		}
		return 'n/A';
	}

	/**
	 * Returns the runtime of the current movie.
	 */
	public function getRuntime() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_RUNTIME, 1));
		}
		return 'n/A';
	}

	/**
	 * Returns the tagline.
	 */
	public function getTagline() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_TAGLINE, 1));
		}
		return 'n/A';
	}

	/**
	 * Get the release date of the current movie.
	 */
	public function getTitle() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_TITLE, 1));
		}
		return 'n/A';
	}

	/**
	 * Returns the url.
	 */
	public function getUrl() {
		return $this->_sUrl;
	}

	/**
	 * Get the votes of the current movie.
	 */
	public function getVotes() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_VOTES, 1));
		}
		return 'n/A';
	}

	/**
	 * Get the year of the current movie.
	 */
	public function getYear() {
		if ($this->_sSource) {
			return implode('', $this->getMatches(self::IMDB_TITLE, 2));
		}
		return 'n/A';
	}

	/**
	 * Returns the writer(s).
	 */
	public function getWriter() {
		if ($this->_sSource) {
			$sReturned = $this->getMatches(self::IMDB_WRITER, 4);
			if (is_array($sReturned)) {
				return implode(' / ', $sReturned);
			}
			return $sReturned;
		}
		return 'n/A';
	}

	/**
	 * Returns the writer(s).
	 */
	public function getWriterAsUrl() {
		if ($this->_sSource) {
			$sReturned1 = $this->getMatches(self::IMDB_WRITER, 4);
			$sReturned2 = $this->getMatches(self::IMDB_WRITER, 1);
			if (is_array($sReturned1)) {
				foreach ($sReturned1 as $i => $sWriter) {
					$aReturn[] = '<a href="http://www.imdb.com/name/' . $sReturned2[$i] . '/">' . $sWriter . '</a>';
				}
				return implode(' / ', $aReturn);
			}
			return '<a href="http://www.imdb.com/name/' . $sReturned2 . '/">' . $sReturned1 . '</a>';
		}
		return 'n/A';
	}
}
