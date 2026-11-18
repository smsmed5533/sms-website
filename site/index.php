<?php
/**
 * SMS Medical website: serves index.html for every page address, with the right
 * title, description and canonical link for that address (so Google sees real pages).
 * The page itself picks the view from the address.
 */
$html = @file_get_contents(__DIR__ . '/index.html');
if ($html === false) { http_response_code(500); exit('Site file missing.'); }

$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$path = '/' . trim((string)$path, '/');
if ($path === '/index.php') $path = '/';

$PAGES = [
  '/' => ['SMS Medical | Lab Testing, Mobile Phlebotomy & Imaging in Oak Lawn, IL',
          'Laboratory testing, mobile phlebotomy and bedside imaging for long-term care, home health and physician practices across Chicagoland. CLIA-certified, COLA-accredited lab in Oak Lawn, IL.'],
  '/std-testing' => ['STD Testing in Oak Lawn, IL | Confidential, Fast Results | SMS Medical',
          'Confidential STD testing in Oak Lawn, IL for chlamydia, gonorrhea, syphilis, herpes, trichomoniasis, Mycoplasma genitalium, HIV, and hepatitis. Panels from $78, most results in 24 to 48 hours. Book online.'],
  '/dna-testing' => ['DNA Paternity Testing in Oak Lawn, IL | Legal & Peace of Mind | SMS Medical',
          'DNA paternity testing in Oak Lawn, IL with a painless cheek swab. Peace-of-mind test $150, legal court-accepted test $295. Same-day appointments often available.'],
  '/privacy-policy' => ['Privacy Policy | SMS Medical', 'How Southwest Medical Solutions, Inc. (SMS Medical) collects, uses, and shares information on this website.'],
  '/open-an-account' => ['Open an Account | SMS Medical', 'Request a new SMS Medical client account for your practice or facility: lab testing, mobile phlebotomy, STAT service, and mobile imaging.'],
  '/mobile-phlebotomy' => ['Mobile Phlebotomy | Long-Term Care, Home Health & Physician Offices | SMS Medical', 'Mobile phlebotomy for skilled nursing, assisted and independent living, home health agencies, home physician groups, and physician offices across Chicagoland. STAT service and courier pickup.'],
  '/pgx-testing' => ['PGx Pharmacogenomics Testing | SMS Medical', 'A 20-gene cheek-swab pharmacogenomics panel with CPIC- and FDA-referenced prescribing guidance, for providers. Ordering criteria and collection steps.'],
  '/billing-insurance' => ['Billing & Insurance | SMS Medical', 'Insurance plans SMS Medical bills, what to expect after your test, Medicare ABNs, self-pay prices, and your right to a Good Faith Estimate.'],
  '/careers' => ['Careers | SMS Medical', 'Join SMS Medical: mobile phlebotomists, lab technologists, specimen processors, couriers, client services, sales, and UDS mobile imaging technologists.'],
  '/forms' => ['Forms & Requisitions | SMS Medical', 'Download the SMS Medical laboratory requisition and the UDS mobile exam order form, or request supplies and courier pickups online.'],
  '/courier-pickup' => ['Request a Courier Pickup | SMS Medical', 'Schedule a routine specimen pickup for your practice or facility. STATs are called in to (708) 424-9405.'],
  '/patients' => ['Patients | STD, DNA & Blood Work in Oak Lawn, IL | SMS Medical', 'Walk in or book a visit at the SMS Medical patient center in Oak Lawn for STD testing, DNA paternity testing, or blood work with your provider\'s order.'],
  '/peptide-panel' => ['Peptide & GLP-1 Monitoring Lab Panel in Oak Lawn, IL | SMS Medical', 'On semaglutide, tirzepatide, or another peptide therapy? One $150 blood draw in Oak Lawn checks blood sugar, kidneys, liver, pancreas, thyroid, and cholesterol, with an easy-to-read report.'],
  '/specialty-testing' => ['Specialty Testing: Molecular & Toxicology | SMS Medical', 'Molecular (PCR) panels and toxicology testing from SMS Medical, ordered on the same requisition as routine labs.'],
  '/terms-of-use' => ['Terms of Use | SMS Medical', 'The terms for using the Southwest Medical Solutions, Inc. (SMS Medical) website.'],
  '/test-directory' => ['Test Directory | SMS Medical', 'Search every lab test and imaging exam SMS Medical offers by name, CPT code, or test code, with specimen requirements.'],
  '/blog' => ['Health Articles | SMS Medical Blog', 'Plain-language articles on testing, sexual health, and wellbeing from the SMS Medical team in Oak Lawn, IL.'],
  '/faq' => ['FAQ | SMS Medical', 'Answers for patients, providers, and facilities: appointments, results, insurance, STAT service, imaging, and critical values.'],
  '/order-supplies' => ['Order Supplies | SMS Medical Client Accounts', 'Order SST and lavender tubes, eSwabs, UTM, urine and sterile cups, requisitions, and specimen bags for your SMS Medical account.'],
  '/blogs/the-stigma-surrounding-stds' => ['The Stigma Surrounding STDs | SMS Medical', 'Stigma keeps people from getting tested and treated. Where it comes from and how to push back on it.'],
  '/blogs/relationship-between-stds-and-mental-health' => ['The Relationship Between STDs and Mental Health | SMS Medical', 'A diagnosis can bring anxiety, guilt, and isolation. Why caring for your mental health is part of caring for your health.'],
  '/blogs/the-often-overlooked-std-trich' => ['The Often Overlooked STD: Trichomoniasis | SMS Medical', 'Trich is common, often has no symptoms, and is easy to treat once it is found. Symptoms, testing, treatment, and prevention.'],
];

$status = 200;
if (!isset($PAGES[$path])) { $status = 404; $path = '/'; }
list($title, $desc) = $PAGES[$path];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^a-z0-9.\-:]/i', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'smsmedical.net');
$url = $scheme . '://' . $host . ($path === '/' ? '/' : $path);
$e = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

$head = "<base href=\"/\">\n"
      . '<link rel="canonical" href="' . $e($url) . "\">\n"
      . '<meta property="og:type" content="website">' . "\n"
      . '<meta property="og:site_name" content="SMS Medical">' . "\n"
      . '<meta property="og:title" content="' . $e($title) . "\">\n"
      . '<meta property="og:description" content="' . $e($desc) . "\">\n"
      . '<meta property="og:url" content="' . $e($url) . "\">\n"
      . '<meta property="og:image" content="' . $e($scheme . '://' . $host . '/img/share.jpg') . "\">\n"
      . '<meta property="og:image:width" content="1200">' . "\n"
      . '<meta property="og:image:height" content="630">' . "\n"
      . '<meta property="og:image:alt" content="SMS Medical: Better care begins with answers.">' . "\n"
      . '<meta name="twitter:card" content="summary_large_image">' . "\n";
if ($path === '/') {
  $ld = [
    '@context' => 'https://schema.org', '@type' => 'MedicalBusiness', 'name' => 'SMS Medical', 'legalName' => 'Southwest Medical Solutions, Inc.',
    'description' => $desc, 'url' => $scheme . '://' . $host . '/', 'telephone' => '+1-708-424-9405', 'faxNumber' => '+1-708-424-8038',
    'email' => 'info@smsmedical.net',
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => '5533 W 109th St, Suite 101', 'addressLocality' => 'Oak Lawn', 'addressRegion' => 'IL', 'postalCode' => '60453', 'addressCountry' => 'US'],
    'openingHoursSpecification' => [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '09:00', 'closes' => '17:00']],
    'sameAs' => ['https://www.facebook.com/smsmedicalinc/', 'https://www.linkedin.com/company/sms-medical-inc/'],
  ];
  $head .= '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}

// staging copy (new.smsmedical.net) stays out of search results
if (stripos($host, 'new.') === 0) { $head .= '<meta name="robots" content="noindex, nofollow">' . "\n"; header('X-Robots-Tag: noindex, nofollow'); }

$html = preg_replace('#<title>.*?</title>#s', '<title>' . $e($title) . '</title>', $html, 1);
$html = preg_replace('#<meta name="description" content="[^"]*">#', '<meta name="description" content="' . $e($desc) . '">', $html, 1);
$html = preg_replace('#<meta charset="utf-8">#', "<meta charset=\"utf-8\">\n" . $head, $html, 1);

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
echo $html;
