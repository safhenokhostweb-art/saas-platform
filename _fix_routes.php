<?php 
 = file_get_contents('c:/laragon/www/saas-platform/routes/web.php'); 
 = 'SupportTicketController as AdminSupportTicketController;'; 
 =  . chr(10) . 'use App\\Http\\Controllers\\Admin\\SupportTicketSettingsController;'; 
 = str_replace(, , ); 
 = qRoute::patch('support-tickets/{support_ticket}/assign', [AdminSupportTicketController::class, 'assign'])-;q; 
