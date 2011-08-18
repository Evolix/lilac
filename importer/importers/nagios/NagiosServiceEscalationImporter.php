<?php
require_once('NagiosEscalation.php');

class NagiosServiceEscalationImporter extends NagiosImporter {

	private static $templates = array();	// Used to support template inheritance when importing this type of obj

	private $regexValidators = array('host_name' => '',
									 'hostgroup_name' => '',
									 'service_description' => '',
									 'contacts' => '',
                                     'contact_groups' => '',
									 'first_notification' => '',
									 'last_notification' => '',
									 'notification_interval' => '',
									 'escalation_period' => '',
									 'escalation_options' => '',
									 'name' => '',
									 'use' => '',
									 'register' => '');
								
	private $fieldMethods = array('first_notification' => 'setFirstNotification',
								  'last_notification' => 'setLastNotification',
								  'notification_interval' => 'setNotificationInterval',
                                  'escalation_period' => 'setEscalationPeriodByName');
								
	// We should gather all the cfg_file and cfg_dir directives and add them to our NagiosImportEngine's object files
	public function init() {
		$config = $this->getEngine()->getConfig();
		$job = $this->getEngine()->getJob();
		$segment = $this->getSegment();
		
		$values = $segment->getValues();
		foreach($values as $key => $entry) {
			foreach($entry as $lineValue) {
				$value = $lineValue['value'];
				$lineNum = $lineValue['line'];

				if(!key_exists($key, $this->regexValidators)) {
					$job->addLogEntry("Variable in service escalation object file not supported: " . $key . " on line " . $lineNum);
					if(!$config->getVar('continue_error')) {
						return false;
					}
				}
				else {
					// Key exists, let's check the regexp
					if($this->regexValidators[$key] != '' && !preg_match($this->regexValidators[$key], $value)) {
						// Failed the regular expression match (which was provided)!!!
						$job->addLogEntry("Variable '" . $key . "' failed the regular expression sanity check of: " . $this->regexValidators[$key] . " on line " . $linenum);
						if(!$config->getVar('continue_error')) {
							return false;
						}
					}
				}
			}
		}
		$job->addNotice("NagiosServiceEscalationImporter finished initializing.");
		return true;
	}

	public static function getTemplateByName($name) {
		if(!key_exists($name, NagiosServiceEscalationImporter::$templates)) {
			return false;
		}
		return NagiosServiceEscalationImporter::$templates[$name];
	}
	
	public function valid() {
		$values = $this->getSegment()->getValues();
		$job = $this->getEngine()->getJob();
		if(isset($values['use'])) {
			// We need to use a template
			$job->addNotice("This Service Escalation uses a template: " . $values['use'][0]['value']);	
			$template = NagiosServiceEscalationImporter::getTemplateByName($values['use'][0]['value']);
			if(empty($template)) {
				$job->addNotice("That template is not found yet. Setting this service escalation as queued.");
				return false;
			}
		}
		// Check time period existence
		if(isset($values['escalation_period'])) {
			$c = new Criteria();
			$c->add(NagiosTimeperiodPeer::NAME, $values['escalation_period'][0]['value']);
			$timePeriod = NagiosTimeperiodPeer::doSelectOne($c);
			if(empty($timePeriod)) {
				$job->addNotice("The time period specified by " . $values['escalation_period'][0]['value'] . " was not found.  Setting this service escalation as queued.");
				return false;
			}
			$timePeriod->clearAllReferences(true);
		}

		// Check contact groups
		if(isset($values['contact_groups'])) {
			foreach($values['contact_groups'] as $contactGroupValues) {
				$c = new Criteria();
				$c->add(NagiosContactGroupPeer::NAME, $contactGroupValues['value']);
				$contactgroup = NagiosContactGroupPeer::doSelectOne($c);
				if(empty($contactgroup)) {
					$job->addNotice("The contact group specified by " . $contactGroupValues['value'] . " was not found.  Setting this service escalation as queued.");
					return false;
				}
				$contactgroup->clearAllReferences(true);
			}
		}

		// Check contacts
		if(isset($values['contacts'])) {
			foreach($values['contacts'] as $contactValues) {
				$c = new Criteria();
				$c->add(NagiosContactPeer::NAME, $contactValues['value']);
				$contact = NagiosContactPeer::doSelectOne($c);
				if(empty($contact)) {
					$job->addNotice("The contact specified by " . $contactValues['value'] . " was not found.  Setting this service escalation as queued.");
					return false;
				}
				$contact->clearAllReferences(true);
			}
		}

		if(isset($values['host_name'])) {
			foreach($values['host_name'] as $hostValues) {
				$service = NagiosServicePeer::getByHostAndDescription($hostValues['value'], $values['service_description'][0]['value']);
				if(empty($service)) {
					$job->addNotice("The service specified by " . $hostValues['value'] . ":" . $values['service_description'][0]['value'] . " was not found.  Setting this host escalation as queued.");
					return false;
				}
				$service->clearAllReferences(true);
			}
		}

		// Check for hostgroup_name
		if(isset($values['hostgroup_name'])) {
			foreach($values['hostgroup_name'] as $hostgroupValues) {
				$service = NagiosServicePeer::getByHostgroupAndDescription($hostgroupValues['value'], $values['service_description'][0]['value']);
				if(empty($service)) {
					$job->addNotice("The service specified by hostgroup " . $hostgroupValues['value'] . ":" . $values['service_description'][0]['value'] . " was not found.  Setting this host escalation as queued.");
					return false;
				}
				$service->clearAllReferences(true);
			}
		}

		return true;

	}

	private function getTemplateValues($name) {
		$job = $this->getEngine()->getJob();
		$template = NagiosServiceEscalationImporter::getTemplateByName($name);
		if(!$template) {
			$job->addNotice("FATAL ERROR: Failed to get template by name: " . $name);
		}
		// $template is a segment instance
		$values = $template->getValues();
		if(isset($values['use'])) {
			// Multiple levels!
			$tempValues = $this->getTemplateValues($values['use'][0]['value']);
			// Okay, go through each
			foreach($tempValues as $key => $val) {
				if(!isset($values[$key])) {
					$values[$key] = $val;
				}
			}	
		}
		return $values;
	}

	static function saveTemplate($name, $segment) {
		NagiosServiceEscalationImporter::$templates[$name] = $segment;
	}

	private function __process($escalation) {
		$job = $this->getEngine()->getJob();
		$config = $this->getEngine()->getConfig();
		$segment = $this->getSegment();
		$values = $segment->getValues();
		$fileName = $segment->getFilename();
		// Check if we need to bring in values from a template
		if(isset($values['use'])) {
			// We sure are using a template!
			// Okay, hokey multi-inheritance support for the importer
			$tempValues = $this->getTemplateValues($values['use'][0]['value']);
			// Okay, go through each
			foreach($tempValues as $key => $val) {
				if(!isset($values[$key])) {
					$values[$key] = $val;
				}
			}	
		}

		foreach($values as $key => $entries) {
			foreach($entries as $entry) {
				// Skips
				$value = $entry['value'];
				$lineNum = $entry['line'];
				if($key == 'use' || $key == 'name' || $key == 'register' || $key == 'host_name' || $key == 'hostgroup_name' || $key == 'contacts' || $key == 'contact_groups' || $key == 'service_description')
					continue;

				if($key == 'escalation_options') {
					$options = explode(",",$entry['value']);
					foreach($options as $option) {
						switch(strtolower(trim($option))) {
							case 'w':
								$escalation->setEscalationOptionsWarning(true);
								break;
							case 'u':
								$escalation->setEscalationOptionsUnknown(true);
								break;
							case 'c':
								$escalation->setEscalationOptionsCritical(true);
								break;
							case 'r':
								$escalation->setEscalationOptionsOk(true);
								break;
						}

					}
					continue;
				}
				// Okay, let's check that the method DOES exist
				if(!method_exists($escalation, $this->fieldMethods[$key])) {
					$job->addError("Method " . $this->fieldMethods[$key] . " does not exist for variable: " . $key . " on line " . $lineNum . " in file " . $fileName);
					if(!$config->getVar('continue_error')) {
						return false;
					}	
				}
				else {
					call_user_method($this->fieldMethods[$key], $escalation, $value);
				}
		
			}

		}
		return true;
	}

	private function __addContacts($escalation) {
		$job = $this->getEngine()->getJob();
		$config = $this->getEngine()->getConfig();
		$segment = $this->getSegment();
		$values = $segment->getValues();
		$fileName = $segment->getFilename();
		// Check if we need to bring in values from a template
		if(isset($values['use'])) {
			// We sure are using a template!
			// Okay, hokey multi-inheritance support for the importer
			$tempValues = $this->getTemplateValues($values['use'][0]['value']);
			// Okay, go through each
			foreach($tempValues as $key => $val) {
				if(!isset($values[$key])) {
					$values[$key] = $val;
				}
			}	
		}
		if(isset($values['contacts'])) {
			$contactNames = explode(",", $values['contacts'][0]['value']);
			foreach($contactNames as $contact_name) {
				$contact = NagiosContactPeer::getByName($contact_name);
				if(!$contact)
					return false;
				$relationship = new NagiosEscalationContact();
				$relationship->setNagiosContact($contact);
				$relationship->setNagiosEscalation($escalation);
				$relationship->save();
				$contact->clearAllReferences(true);
				$relationship->clearAllReferences(true);
			}
		}
		if(isset($values['contact_groups'])) {
			$contactGroupNames = explode(",", $values['contact_groups'][0]['value']);
			foreach($contactGroupNames as $contactgroup_name) {
				$contactgroup = NagiosContactGroupPeer::getByName($contactgroup_name);
				if(!$contactgroup)
					return false;
				$relationship = new NagiosEscalationContactGroup();
				$relationship->setNagiosContactGroup($contactgroup);
				$relationship->setNagiosEscalation($escalation);
				$relationship->save();
				$contactgroup->clearAllReferences(true);
				$relationship->clearAllReferences(true);
			}
		}
		return true;
	}
	
	public function import() {
		$job = $this->getEngine()->getJob();
		$config = $this->getEngine()->getConfig();
		$segment = $this->getSegment();
		$values = $segment->getValues();
		$fileName = $segment->getFilename();
		// We need to determine if we are a template
		if(isset($values['name'])) {
			// We are a template
			$job->addNotice("Saving internal service escalation template: " . $values['name'][0]['value']);
			NagiosServiceEscalationImporter::saveTemplate($values['name'][0]['value'], $segment);
			return true;
		}		
		// Check if we need to bring in values from a template
		if(isset($values['use'])) {
			// We sure are using a template!
			// Okay, hokey multi-inheritance support for the importer
			$tempValues = $this->getTemplateValues($values['use'][0]['value']);
			// Okay, go through each
			foreach($tempValues as $key => $val) {
				if(!isset($values[$key])) {
					$values[$key] = $val;
				}
			}	
		}
		// Okay, we first iterate through any possible dependent_host_name's
		if(isset($values['host_name'])) {
			$host_names = explode(",", $values['host_name'][0]['value']);
			foreach($host_names as $host_name) {
				$escalation = new NagiosEscalation();
				$service = NagiosServicePeer::getByHostAndDescription($host_name, $values['service_description'][0]['value']);
				if(!$service)
					return false;
				$escalation->setNagiosService($service);
				$ret = $this->__process($escalation);
				if(!$ret)
					return false;
				$ret = $this->__addContacts($escalation);
				if(!$ret)
					return false;
				// Need to give it a temp name
				$escalation->save();
				$escalation->setDescription("Imported Escalation #" . $escalation->getId());
				$escalation->save();
				$escalation->clearAllReferences(true);
				$service->clearAllReferences(true);
				$job->addNotice("NagiosServiceEscalationImporter finished importing Service Escalation for " . $host_name); 
			}
		}
		if(isset($values['hostgroup_name'])) {
			$hostgroup_names = explode(",", $values['hostgroup_name'][0]['value']);
			foreach($hostgroup_names as $hostgroup_name) {
				$escalation = new NagiosEscalation();
				$service = NagiosServicePeer::getByHostgroupAndDescription($hostgroup_name, $values['service_description'][0]['value']);
				if(!$service)
					return false;
				$escalation->setNagiosService($service);
				$ret = $this->__process($escalation);
				if(!$ret)
					return false;
				$ret = $this->__addContacts($escalation);
				if(!$ret)
					return false;
				$escalation->save();
				$escalation->setDescription("Imported Escalation #" . $escalation->getId());
				$escalation->save();
				$escalation->clearAllReferences(true);
				$service->clearAllReferences(true);
				$job->addNotice("NagiosServiceEscalationImporter finished importing Service Escalation for hostgroup " . $hostgroup_name);
			}
		}
		return true;
	}
}

?>
