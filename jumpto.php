<?php

# Class to provide a tinyUrl-style service with keyed names
class jumpto
{
	# Supported actions
	var $actions = array (
		'redirect' => 'Redirection',
		'index' => 'Show current jumpto entries',
		'add' => 'Add a jumpto entry',
		'remove' => 'Remove a jumpto entry',
	);
	
	# Defaults
	private function defaults ()
	{
		# Define and return the settings
		return $settings = array (
			'username'		=> NULL,
			'password'		=> NULL,
			'hostname'		=> 'localhost',
			'database'		=> NULL,
			'table'			=> 'jumpto',
			'404page'		=> 'sitetech/404.html',
			'webmasterEmail'	=> NULL,
			'chopTo'		=> 38,
			'banInternal'		=> true,
		);
	}
	
	
	# Database structure
	private function databaseStructure ()
	{
		return $sql = "
			CREATE TABLE IF NOT EXISTS `jumpto_jumpto` (
			  `id` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
			  `url` text COLLATE utf8_unicode_ci NOT NULL,
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `addedby` int(5) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Jumpto facility';
		";
	}
	
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Load required libraries
		require_once ('application.php');
		require_once ('database.php');
		require_once ('ultimateForm.php');
		require_once ('signin/signin.php');
		
		# Get the base URL
		$this->baseUrl = application::getBaseUrl ();
		
		# Assign the settings
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults (), get_class ($this), NULL, $handleErrors = true)) {return false;}
		
		# Connect to the database or end
		$this->databaseConnection = new database ($this->settings['hostname'], $this->settings['username'], $this->settings['password'], $this->settings['database']);
		if (!$this->databaseConnection->connection) {
			$message = 'There was a problem with initalising the jumpto facility at the database connection stage. MySQL said: ' . mysql_error () . '.';
			mail ($this->settings['webmasterEmail'], 'Problem with jumpto system on ' . $_SERVER['SERVER_NAME'], wordwrap ($message));
			echo "\n<p class=\"warning\">Apologies - this facility is currently unavailable, as a technical error occured. The Webmaster has been informed and will investigate.</p>";
			return false;
		};
		
		# Assign the action
		$action = (isSet ($_GET['action']) ? $_GET['action'] : false);
		
		# Validate the action if supplied
		if (!$action || !array_key_exists ($action, $this->actions)) {
			$this->page404 ();
			return false;
		}
		
		# If not redirecting, ensure the user is an administrator
		if (($action != 'redirect') && (!signin::get_id () || !signin::user_has_privilege ('jumpto'))) {
			echo "\n<h1>Administer jumpto entries</h1>";
			echo "\n<p>You must " . (signin::get_id () ? 'have jumpto rights' : '<a href="' . signin::user_signin_page() . '">sign in with jumpto rights</a>') . ' to administer this facility.</p>';
			return;
		}
		
		# Take action then end
		$this->{$action} ();
	}
	
	
	# Function to show a small menu
	private function showMenu ()
	{
		# Construct the list
		echo "\n" . '<ul>
			<li><a href="./">Show current jumpto entries</a></li>
			<li><a href="add.html">Add a jumpto entry</a></li>
		</ul>
		<br />';
		
	}
	
	
	# Page not found wrapper
	private function page404 ()
	{
		# Create a 404 page
		header ('HTTP/1.0 404 Not Found');
		include ($this->settings['404page']);
	}
	
	
	# Main redirection function
	private function redirect ()
	{
		# Ensure a value is supplied
		if (!isSet ($_GET['redirect']) || !strlen ($_GET['redirect'])) {
			$this->page404 ();
			return false;
		}
		
		# Select all from the table
		$query = "SELECT * FROM {$this->settings['table']} WHERE id = :id;";
		$preparedStatementValues = array ('id' => $_GET['redirect']);
		
		# Get the data
		if (!$data = $this->databaseConnection->getOne ($query, false, true, $preparedStatementValues)) {
			$this->page404 ();
			return false;
		}
		
		# Perform the redirection
		header ("Location: {$data['url']}");
	}
	
	
	# Show all redirects
	private function index ($showHeading = true)
	{
		# Show the heading and menu
		if ($showHeading) {
			echo "\n<h1>Show current jumpto entries</h1>";
			$this->showMenu ();
		}
		
		# Select all from the table
		$query = "SELECT
			id,
			CONCAT('<a href=\"{$this->baseUrl}/',id,'\" target=\"_blank\">',id,'</a>') as 'Jump to',
			url,
			/* FROM_UNIXTIME(UNIX_TIMESTAMP(timestamp), '%l:%i%p, %e/%c/%Y') as Timestamp, */
			addedby as 'Added by user'
		FROM {$this->settings['table']}
		ORDER BY id;";
		
		# Get the data
		if (!$data = $this->databaseConnection->getData ($query)) {
			echo "\n<p class=\"warning\">There are no entries in the database. You can <a href=\"add.html\">add one</a>.</p>";
			return;
		}
		
		# Chop the URL for visibility
		foreach ($data as $index => $item) {
			$data[$index]['url'] = "<a href=\"{$item['url']}\" target=\"_blank\">" . ((strlen ($item['url']) > $this->settings['chopTo']) ? substr ($item['url'], 0, $this->settings['chopTo']) . '</a><strong>...</strong>' : ($item['url'] . '</a>'));
			$data[$index]['Remove?'] = "<a href=\"remove.html?url={$item['id']}\">Remove</a>";
			unset ($data[$index]['id']);
		}
		
		# Show the HTML
		echo application::htmlTable ($data, $tableHeadingSubstitutions = array (), $class = 'lines', $showKey = false, $uppercaseHeadings = false, $allowHtml = true);
	}
	
	
	# Addition form
	private function add ()
	{
		# Show the heading and menu
		echo "\n<h1>Add a jumpto entry</h1>";
		$this->showMenu ();
		
		# Create the form
		$form = new form (array (
			'displayDescriptions' => true,
			'displayRestrictions' => false,
			'formCompleteText' => false,
		));
		
		# Widgets
		$form->input (array (
			'name'		=> 'id',
			'title'		=> 'Key name',
			'description'	=> "This is what will be used for<br />{$_SERVER['SERVER_NAME']}{$this->baseUrl}/<em>something</em>",
			'required'	=> true,
			'regexp'	=> '^([0-9a-zA-Z-]+)$',
			'maxlength'	=> 25,
		));
		$form->input (array (
			'name'		=> 'url',
			'title'		=> 'URL in full',
			'required'	=> true,
			'regexp'	=> '^(http|https)://',
			'maxlength'	=> 4096,
			'size'		=> 50,
			'default'	=> 'https://',
		));
		
		# Process the form
		if ($result = $form->process ()) {
			
			# If internal URLs are banned, check this
			if ($this->settings['banInternal']) {
				if (strpos ($result['url'], '//' . $_SERVER['SERVER_NAME']) !== false) {
					echo "\n<p class=\"warning\">Sorry, URLs to jump to cannot be from within this site itself.<br />(This is to stop proliferation of unnecessary URLs.)</p>";
					return;
				}
			}
			
			# Ensure that key doesn't exist already
			$query = "SELECT * FROM {$this->settings['table']} WHERE id = :id;";
			$preparedStatementValues = array ('id' => $result['id']);
			if ($data = $this->databaseConnection->getOne ($query, false, true, $preparedStatementValues)) {
				echo "\n<p class=\"warning\"><strong>{$result['id']}</strong> already exists as a jumpto entry. Please go back and use a different key.</p>";
				return;
			}
			
			# Add the entry
			$query = "INSERT INTO {$this->settings['table']} (id,url,addedby) VALUES (:id, :url, :addedby);";
			$preparedStatementValues = array (
				'id' => $result['id'],
				'url' => $result['url'],
				'addedby' => signin::get_id (),		// User ID
			);
			if (!$this->databaseConnection->execute ($query, $preparedStatementValues)) {
				echo "\n<p class=\"warning\">There was a problem entering this item into the database.</p>";
			} else {
				echo "\n<p>CONFIRMED: The jumpto entry <strong>{$result['id']}</strong> was entered into the database.";
				$url = ((substr ($_SERVER['SERVER_NAME'], 0, 3) != 'www') ? 'https://' : '') . $_SERVER['SERVER_NAME'] . $this->baseUrl . '/' . $result['id'];
				echo "\n<div class=\"basicbox\">";
				echo "\n<p><strong>You should quote exactly the following URL for the Newsletter or elsewhere:</strong>";
				echo "\n<p>$url</p>";
				echo "\n<p><a href=\"{$this->baseUrl}/{$result['id']}\" target=\"blank\">Test the link now</a> [opens in a new window]</p>";
				echo "\n</div>";
			}
		}
		
		# Show all entries now the list is updated
		echo "\n<p>The list of entries is currently:</p>";
		$this->index ($showHeading = false);
	}
	
	
	# Removal form
	private function remove ()
	{
		# Show the heading and menu
		echo "\n<h1>Delete a jumpto entry</h1>";
		$this->showMenu ();
		
		# Ensure they've selected a valid key
		$found = false;
		if (isSet ($_GET['url'])) {
			if ($data = $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], array ('id' => $_GET['url']))) {
				$found = true;
			}
		}
		
		# Ensure it is found, or end
		if (!$found) {
			echo "\n<p>You appear to have selected a jumpto entry that does not exist. You can select one to delete from the following table:</p>";
			$this->index ($showHeading = false);
			return false;
		}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions' => true,
			'displayRestrictions' => false,
			'formCompleteText' => false,
		));
		
		# Widgets
		$form->input (array (
			'name'		=> 'id',
			'title'		=> 'If you are sure you want to delete this jumpto key, confirm its name here',
			'required'	=> true,
			'regexp'	=> "^{$data['id']}$",
			'maxlength'	=> 25,
		));
		
		# Process the form
		if (!$result = $form->process ()) {return false;}
		
		# Delete the entry
		$query = "DELETE FROM {$this->settings['table']} WHERE id = :id LIMIT 1;";
		$preparedStatementValues = array ('id' => $data['id']);
		if (!$this->databaseConnection->execute ($query, $preparedStatementValues)) {
			echo "\n<p class=\"warning\">There was a problem deleting this item into the database.</p>";
		}
		
		# Confirm success
		echo "\n<p>CONFIRMED: The jumpto entry <strong>{$result['id']}</strong> was deleted from the database.</p>";
		$this->index ($showHeading = false);
	}
}

?>
