<?php
session_start();
?>
<html>
<head>
	<title>Domain explorer</title>
</head>
<body>
	<h1>Domain explorer</h1>
	<form action="index.php" method="get">
		<p>This simple tool will let you look up details of website, email, and DNS hosting for any number of domains.</p>
		<p>Enter domains, without "www.", one per line:</p>
		<p><textarea name="Domains" rows="10" cols="20"><?php if(isset($_REQUEST['Domains'])) echo $_REQUEST['Domains']; ?></textarea></p>
		<p><input type="submit" value="Explore!"></p>
	</form>
	
<?php 

if(isset($_REQUEST['Domains'])) { 
	if(isset($_REQUEST['Flush'])) $_SESSION['domainInfo'] = array();
	
	$domainInfo = array();
	$domains = explode("\n", trim($_REQUEST['Domains']));
	foreach($domains as $domain) {
		$domain = trim($domain);
		if(!$domain) continue;
		$domain = preg_replace('/^www\./','',$domain);
		$domainInfo[] = domainInfo($domain);
	}
	
	if(isset($_REQUEST['Sort'])) {	
		usort($domainInfo, create_function('$a, $b', 'return strcmp($a[$_REQUEST["Sort"]], $b[$_REQUEST["Sort"]]);'));
	}

	if($domainInfo) {
		echo "<h2>Domain information</h2>
			<table>
			<tr>\n";
			$headers = array_keys(reset($domainInfo));
			foreach($headers as $header) {
				echo "<th><a href=\"index.php?Domains=" . urlencode($_REQUEST['Domains']) ."&Sort=" . urlencode($header) . "\">" . htmlentities($header) . "</a></th>\n";
			}
			foreach($domainInfo as $record) {
				echo "<tr>\n";
				foreach($headers as $header) {
					echo "<td>" . htmlentities($record[$header]) . "</td>\n";
				}
				echo "</tr>\n";
			}
		echo "</tr>\n</table>\n";
	}
} 

/**
 * Return the reverse-DNS hostname from the given IP address
 */
function revdns($ip) {
	$CLI_ip = escapeshellarg($ip);
	return trim(`host $CLI_ip | grep "domain name pointer" | head -n 1| awk "{ print \\\$5 }"`);
}

function domainInfo($domain) {
	if(!isset($_SESSION['domainInfo'][$domain])) {
		$CLI_domain = escapeshellarg($domain);
		$CLI_wwwdomain = escapeshellarg("www.$domain");
		
		$websiteip = $websitehost = $nameserver = $mailhost = $mailip = $revhost = "";
		$registrar = trim(str_replace('registrar_name:','', `whois $CLI_domain | grep registrar_name`));

		$nameserver = trim(`dig ns $CLI_domain | grep "ANSWER SECTION" --after 1 | tail -n 1 | awk "{ print \\\$5 }"`);
		if($nameserver) {
			$websiteip = trim(`host $CLI_wwwdomain | grep "has address" | head -n 1| awk "{ print \\\$4 }"`);
			if($websiteip) {
				$websitehost = revdns($websiteip);
			}
		
			$mailhost = trim(`dig mx $CLI_domain | grep "ANSWER SECTION" --after 1 | tail -n 1 | awk "{ print \\\$6 }"`);
			if($mailhost) {
				$CLI_mailhost = escapeshellarg($mailhost);
				$mailip=trim(`host $CLI_mailhost | grep "has address" | head -n 1| awk "{ print \\\$4 }"`);
				if($mailip) {
					$revhost = revdns($mailip);
				}
			}
		}
		$_SESSION['domainInfo'][$domain] = array(
			'Domain' => $domain,
			'Registrar' => $registrar,
			'Nameserver' => $nameserver,
			'Website Host' => $websitehost,
			'Website IP' => $websiteip,
			'Mail Host' => $revhost ? $revhost : $mailhost,
			'Mail IP' => $mailip,
		);
		sleep(1);
	}
	
	return $_SESSION['domainInfo'][$domain];
}

?>
</body>
</html>