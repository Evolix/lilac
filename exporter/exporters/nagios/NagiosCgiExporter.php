<?php

class NagiosCgiExporter extends NagiosExporter {
	
	public function init() {
		return true;
	}
	
	public function valid() {
		return true;
	}
	
	public function export() {
		// Grab our export job
		$engine = $this->getEngine();
		$job = $engine->getJob();
		$job->addNotice("NagiosCgiExporter attempting to export cgi configuration.");
		
		// Grab our cgi config
		$cgiConfig = NagiosCgiConfigurationPeer::doSelectOne(new Criteria());
		if(!$cgiConfig) {
			$job->addError("Unable to get CGI Configuration object.  Your Lilac database is corrupt.");
			return false;
		}

		$finalArray = array();
		
		$values = $cgiConfig->toArray(BasePeer::TYPE_FIELDNAME);
		foreach($values as $key => $value) {
			if($key == 'id') {
				continue;
			}
			if($value === null) {
				continue;
			}
			if($value === false) {
				$value = '0';
			}
			$finalArray[$key] = $value;
		}

		// get our main config
		$mainConfig = NagiosMainConfigurationPeer::doSelectOne(new Criteria());

		$configdir = $mainConfig->getConfigDir();

		$finalArray['main_config_file'] = $configdir . "/nagios.cfg";

		$fp = $this->getOutputFile();
		fputs($fp, "# Written by NagiosCgiExporter from " . LILAC_NAME . " " . LILAC_VERSION . " on " . date("F j, Y, g:i a") . "\n\n");
		foreach($finalArray as $key => $val) {
			fputs($fp, $key . "=" . $val . "\n");
		}

		/*    Added by Romain Dessort (Evolix) on 08/02/2011:
		 *
		 *  nagios_check_command directive is required by Nagios, but
		 *  Lilac does not allow to change this parameter in the web
		 *  interface. I set it here.
		 */
		fputs($fp, "nagios_check_command=/usr/lib/nagios/plugins/check_nagios /var/cache/nagios3/status.dat 5 '/usr/sbin/nagios3'\n");

		$job->addNotice("NagiosCgiExporter complete.");
		return true;
	}
	
}

?>
