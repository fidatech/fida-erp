<?php
namespace App\Services\ERP;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use RuntimeException;

final class InvoicePdfService
{
 public function render(int $invoiceId,string $locale): array
 {
  $db=DB::connection('erp_tenant');
  $invoice=$db->table('sales_invoices as s')->join('customers as c','c.id','=','s.customer_id')
      ->where('s.id',$invoiceId)->select('s.*','c.name as customer_name','c.address as customer_address')->first();
  if(!$invoice)abort(404);
  $lines=$db->table('sales_invoice_lines as l')->join('product_variants as v','v.id','=','l.product_variant_id')
      ->join('products as p','p.id','=','v.product_id')->where('l.sales_invoice_id',$invoiceId)
      ->select('l.*','v.sku','p.name as product_name')->orderBy('l.id')->get();
  $rtl=in_array($locale,['fa','ps'],true);
  $fontDirectory=storage_path('app/fonts');
  $fontFile=$fontDirectory.'/Vazirmatn-Regular.ttf';
  if($rtl && !is_file($fontFile))throw new RuntimeException('Install Vazirmatn-Regular.ttf in storage/app/fonts before printing Dari or Pashto PDFs');
  $config=(new ConfigVariables())->getDefaults();$fonts=(new FontVariables())->getDefaults();
  $fontdata=$fonts['fontdata'];
  if(is_file($fontFile)) {
     $fontdata['vazirmatn']=['R'=>'Vazirmatn-Regular.ttf','useOTL'=>0xFF,'useKashida'=>75];
  }
  $tmp=storage_path('app/private/mpdf');if(!is_dir($tmp) && !mkdir($tmp,0700,true) && !is_dir($tmp))throw new RuntimeException('Unable to create private mPDF temp directory');
  $mpdf=new Mpdf(['format'=>'A4','mode'=>'utf-8','tempDir'=>$tmp,
      'fontDir'=>array_merge($config['fontDir'],[$fontDirectory]),'fontdata'=>$fontdata,
      'default_font'=>is_file($fontFile)?'vazirmatn':'dejavusans','directionality'=>$rtl?'rtl':'ltr']);
  $html=view('erp.pdf.invoice',['invoice'=>$invoice,'lines'=>$lines,'company'=>$db->table('company_settings')->first(),
      'locale'=>$locale,'rtl'=>$rtl])->render();
  $mpdf->WriteHTML($html);
  return [$mpdf->Output('',\Mpdf\Output\Destination::STRING_RETURN),$invoice->number];
 }
}
