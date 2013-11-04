<?php
include('whois.main.php');
$domain_array = array(
   'athleticwork.se',
   'athleticwork.com',
   'athleticwork.org',
   'athleticwork.net',
   'athleticwork.co.uk',
   'athleticwork.org.uk',
   'athleticwork.info',
   'athleticstaffing.se',
   'athleticstaffing.com',
   'korttidsbemanning.se',
);
$whois = new Whois();
echo('<ul>');
foreach ( $domain_array as $domain_item )
{
   $result = "";
   $result = $whois->Lookup($domain_item);
   if ( array_key_exists('expires', $result['regrinfo']['domain']) )
   {
        $expires = $result['regrinfo']['domain']['expires'];
   }else{
       $expires = NULL;
   }
   
   if ( $expires === NULL )
   {
      $expires = "Unknown";
   }
   echo('<li><strong>'.$domain_item.':</strong><ul><li>'.$expires.'</li></ul></li>');
}
echo('</ul>');
?>
