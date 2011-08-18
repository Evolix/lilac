<?php
/*
Lilac - A Nagios Configuration Tool
Copyright (C) 2007 Taylor Dondich

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/*
 * add_service_template.php
 * Author:	Taylor Dondich (tdondich at gmail.com)
 * Description:
 * 	Provides interface to maintain service templates
 *
*/
include_once('includes/config.inc');

if(isset($_GET['host_template_id'])) {
	$hostTemplate = NagiosHostTemplatePeer::retrieveByPK($_GET['host_template_id']);
	if(!$hostTemplate) {
		header("Location: welcome.php");
		die();
	}
	else {
		$title = " for Host Template " . $hostTemplate->getName();
		$sublink = "?host_template_id=" . $hostTemplate->getId();
		$cancelLink = "host_template.php?id=" . $hostTemplate->getId() . "&section=services";
	}
}
else if(isset($_GET['host_id'])) {
	$host = NagiosHostPeer::retrieveByPK($_GET['host_id']);
	if(!$host) {
		header("Location: welcome.php");
		die();
	}
	else {
		$title = " for Host " . $host->getName();
		$sublink = "?host_id=" . $host->getId();
		$cancelLink = "hosts.php?id=" . $host->getId() . "&section=services";
	}
}
else if(isset($_GET['hostgroup_id'])) {
	$hostgroup = NagiosHostgroupPeer::retrieveByPK($_GET['hostgroup_id']);
	if(!$hostgroup) {
		header("Location: welcome.php");
		die();
	}
	else {
		$title = " for Hostgroup " . $hostgroup->getName();
		$sublink = "?hostgroup_id=" . $hostgroup->getId();
		$cancelLink = "hostgroups.php?id=" . $hostgroup->getId() . "&section=services";
	}
}

else {
	header("Location: welcome.php");
	die();
}

if(isset($_POST['request'])) {
	if($_POST['request'] == 'add_service') {
		if(isset($hostTemplate)) {
			// Template logic
			$c = new Criteria();
			$c->add(NagiosServicePeer::DESCRIPTION, $_POST['service_description']);
			$c->add(NagiosServicePeer::HOST_TEMPLATE, $hostTemplate->getId());
			$c->setIgnoreCase(true);
			$service = NagiosServicePeer::doSelectOne($c);
			if($service) {
				$error = "A service with that description already exists for that host template.";
			}
			else {
				// Let's add.
				$service = new NagiosService();
				$service->setDescription($_POST['service_description']);
                $service->setDisplayName($_POST['display_name']);
				$service->setHostTemplate($hostTemplate->getId());
				$service->save();
				header("Location: service.php?id=" . $service->getId());
				die();
				
			}
		}
		else if(isset($host)) {
			// Host logic
			$c = new Criteria();
			$c->add(NagiosServicePeer::DESCRIPTION, $_POST['service_description']);
			$c->add(NagiosServicePeer::HOST, $host->getId());
			$c->setIgnoreCase(true);
			$service = NagiosServicePeer::doSelectOne($c);
			if($service) {
				$error = "A service with that description already exists for that host.";
			}
			else {
				// Let's add.
				$service = new NagiosService();
				$service->setDescription($_POST['service_description']);
                $service->setDisplayName($_POST['display_name']);
				$service->setHost($host->getId());
				$service->save();
				header("Location: service.php?id=" . $service->getId());
				die();
			}
		}
		else if(isset($hostgroup)) {
			// Host logic
			$c = new Criteria();
			$c->add(NagiosServicePeer::DESCRIPTION, $_POST['service_description']);
			$c->add(NagiosServicePeer::HOSTGROUP, $hostgroup->getId());
			$c->setIgnoreCase(true);
			$service = NagiosServicePeer::doSelectOne($c);
			if($service) {
				$error = "A service with that description already exists for that hostgroup.";
			}
			else {
				// Let's add.
				$service = new NagiosService();
				$service->setDescription($_POST['service_description']);
                $service->setDisplayName($_POST['display_name']);
				$service->setHostgroup($hostgroup->getId());
				$service->save();
				header("Location: service.php?id=" . $service->getId());
				die();
			}
		}
		
		
	}
}

print_header("Service Editor");

// Get list of service templates
$lilac->get_service_template_list($tempList);
$template_list[] = array("service_template_id" => '', "template_name" => "None");
foreach($tempList as $tempTemplate)
	$template_list[] = array('service_template_id' => $tempTemplate->getId(), 'template_name' => $tempTemplate->getName());

	

	
print_window_header("Add Service " . $title, "100%");
?>
<form name="service_template_add_form" method="post" action="add_service.php<?php echo $sublink;?>">
<input type="hidden" name="request" value="add_service" />
<?php double_pane_form_window_start(); ?>
<tr bgcolor="eeeeee">
	<td colspan="2" class="formcell">
	<b>Service Description:</b><br />
	<input type="text" size="40" name="service_description" value=""><br />
	<?php echo $lilac->element_desc("service_description", "nagios_services_desc"); ?><br />
	<br />
	</td>
</tr>
<tr bgcolor="eeeeee">
	<td colspan="2" class="formcell">
	<b>Display Name: (Optional)</b><br />
	<input type="text" size="40" name="display_name" value=""><br />
	<?php echo $lilac->element_desc("display_name", "nagios_services_desc"); ?><br />
	<br />
	</td>
</tr>
<?php double_pane_form_window_finish(); ?>
<input type="submit" value="Add Service" />&nbsp;[ <a href="<?php echo $cancelLink;?>">Cancel</a> ]
<br /><br />
</form>
<?php
print_window_footer();
?>
<br />
<?php
print_footer();
?>
