<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Mail\Message;
use App\User;
use App\Customer;
use App\BusinessSetting;
use RuntimeException;
use Hash;
use Validator;
use Cache;
use Mail;

class MailTestController extends Controller {
 
    public function basic_email() {
      
      $data = array('name'=>"Virat Gandhi");
   
      Mail::send([], $data, function($message) {
         $message->to('manishpatolia09@gmail.com', 'Tutorials Point')
         ->subject('Laravel Basic Testing Mail');
      });
      echo "Basic Email Sent. Check your inbox.";
      exit;
   }
   public function html_email() {
      $data = array('name'=>"Virat Gandhi");
      Mail::send('mail', $data, function($message) {
         $message->to('abc@gmail.com', 'Tutorials Point')->subject
            ('Laravel HTML Testing Mail');
         $message->from('xyz@gmail.com','Virat Gandhi');
      });
      echo "HTML Email Sent. Check your inbox.";
   }
   public function attachment_email() {
      $data = array('name'=>"Virat Gandhi");
      Mail::send('mail', $data, function($message) {
         $message->to('abc@gmail.com', 'Tutorials Point')->subject
            ('Laravel Testing Mail with Attachment');
         $message->attach('C:\laravel-master\laravel\public\uploads\image.png');
         $message->attach('C:\laravel-master\laravel\public\uploads\test.txt');
         $message->from('xyz@gmail.com','Virat Gandhi');
      });
      echo "Email Sent with attachment. Check your inbox.";
   }
}
