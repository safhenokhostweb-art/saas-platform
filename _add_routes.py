import sys 
f=open('c:/laragon/www/saas-platform/routes/web.php','r',encoding='utf-8') 
c=f.read() 
f.close() 
old='SupportTicketController as AdminSupportTicketController;' 
new=old+chr(10)+'use App\\Http\\Controllers\\Admin\\SupportTicketSettingsController;' 
c=c.replace(old,new) 
