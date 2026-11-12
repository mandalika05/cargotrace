{{--
 ============================================================
 BUAT APA : Kerangka halaman SEBELUM LOGIN (login, daftar).
            KONSEP "MENARA KONTROL": satu layar penuh berwarna (tanpa kartu),
            radar yang menyapu, titik shipment berwarna status
            (teal = in transit, hijau = delivered, kuning = delayed, koral = exception),
            dan garis kontur seperti peta pelabuhan. Formulir langsung di atas latar.
 RESPONSIF: laptop = dua kolom (intro kiri, formulir kanan).
            HP = satu kolom, latar tetap penuh, intro diringkas.
 CATATAN  : semua gaya ada di resources/css/auth.css (blok "login-").
            Ilustrasi (.login-art) adalah SVG tertanam, jadi tetap muncul offline.
 CARA PAKAI di halaman:
     @extends('layouts.guest')
     @section('title', 'Sign in')
     @section('content') ... @endsection
 ============================================================
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07302a">
    <title>@yield('title', 'Sign in') - CargoTrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
@php
    // Judul dipecah per baris -> kata -> huruf supaya tiap huruf bisa bergoyang bergantian.
    $headlineLines = [
        ['text' => 'Every shipment,', 'class' => ''],
        ['text' => 'always in sight.', 'class' => 'headline-accent'],
    ];
    $i = 0;
@endphp

<div class="login-scene">

    <div class="login-art" aria-hidden="true">
    <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
      <g class="art-contours" stroke="#F4D58A" stroke-width="1.2">
        <path d="M0 -40.0 L32 -30.6 L64 -21.8 L96 -14.3 L128 -8.5 L160 -4.7 L192 -3.1 L224 -3.7 L256 -6.0 L288 -9.8 L320 -14.5 L352 -19.6 L384 -24.6 L416 -28.9 L448 -32.2 L480 -34.4 L512 -35.4 L544 -35.3 L576 -34.5 L608 -33.4 L640 -32.4 L672 -32.0 L704 -32.6 L736 -34.5 L768 -37.8 L800 -42.6 L832 -48.5 L864 -55.2 L896 -62.3 L928 -69.1 L960 -75.0 L992 -79.5 L1024 -82.1 L1056 -82.5 L1088 -80.6 L1120 -76.4 L1152 -70.2 L1184 -62.5 L1216 -53.7 L1248 -44.6 L1280 -35.8 L1312 -27.9 L1344 -21.4 L1376 -16.6 L1408 -13.7 L1440 -12.6 L1472 -13.3 L1504 -15.2 L1536 -18.0 L1568 -21.1 L1600 -24.1 L1632 -26.5" stroke-opacity="0.07"/>
        <path d="M0 45.1 L32 49.1 L64 50.9 L96 50.7 L128 48.6 L160 45.1 L192 40.6 L224 35.8 L256 31.0 L288 26.8 L320 23.6 L352 21.6 L384 20.7 L416 20.8 L448 21.7 L480 22.9 L512 23.9 L544 24.4 L576 23.8 L608 22.0 L640 18.7 L672 13.9 L704 7.9 L736 1.0 L768 -6.2 L800 -13.2 L832 -19.4 L864 -24.2 L896 -27.2 L928 -28.0 L960 -26.4 L992 -22.5 L1024 -16.6 L1056 -9.1 L1088 -0.5 L1120 8.5 L1152 17.3 L1184 25.3 L1216 31.9 L1248 36.8 L1280 39.9 L1312 41.1 L1344 40.7 L1376 39.0 L1408 36.4 L1440 33.5 L1472 30.7 L1504 28.5 L1536 27.2 L1568 26.9 L1600 27.7 L1632 29.4" stroke-opacity="0.09"/>
        <path d="M0 103.2 L32 100.0 L64 95.7 L96 91.1 L128 86.5 L160 82.5 L192 79.4 L224 77.5 L256 76.7 L288 76.9 L320 77.9 L352 79.1 L384 80.3 L416 80.8 L448 80.3 L480 78.5 L512 75.2 L544 70.4 L576 64.3 L608 57.4 L640 49.9 L672 42.7 L704 36.2 L736 31.1 L768 27.8 L800 26.7 L832 28.0 L864 31.5 L896 37.1 L928 44.4 L960 52.8 L992 61.7 L1024 70.4 L1056 78.4 L1088 85.1 L1120 90.2 L1152 93.4 L1184 94.9 L1216 94.6 L1248 93.1 L1280 90.7 L1312 88.0 L1344 85.4 L1376 83.3 L1408 82.2 L1440 82.1 L1472 83.0 L1504 84.9 L1536 87.3 L1568 89.8 L1600 92.0 L1632 93.4" stroke-opacity="0.11"/>
        <path d="M0 141.9 L32 138.1 L64 135.1 L96 133.3 L128 132.6 L160 133.0 L192 134.0 L224 135.4 L256 136.6 L288 137.2 L320 136.8 L352 135.0 L384 131.7 L416 127.0 L448 120.8 L480 113.7 L512 106.2 L544 98.7 L576 92.0 L608 86.6 L640 82.9 L672 81.5 L704 82.4 L736 85.6 L768 90.9 L800 97.9 L832 106.2 L864 114.9 L896 123.6 L928 131.6 L960 138.4 L992 143.5 L1024 146.9 L1056 148.5 L1088 148.5 L1120 147.2 L1152 145.0 L1184 142.4 L1216 140.0 L1248 138.1 L1280 137.1 L1312 137.1 L1344 138.2 L1376 140.2 L1408 142.8 L1440 145.6 L1472 148.0 L1504 149.5 L1536 149.8 L1568 148.5 L1600 145.3 L1632 140.4" stroke-opacity="0.13"/>
        <path d="M0 188.5 L32 188.9 L64 190.1 L96 191.5 L128 192.9 L160 193.6 L192 193.3 L224 191.6 L256 188.3 L288 183.5 L320 177.4 L352 170.2 L384 162.5 L416 154.8 L448 147.8 L480 142.1 L512 138.2 L544 136.4 L576 136.9 L608 139.8 L640 144.8 L672 151.6 L704 159.6 L736 168.2 L768 176.8 L800 184.8 L832 191.6 L864 196.9 L896 200.4 L928 202.2 L960 202.3 L992 201.2 L1024 199.2 L1056 196.8 L1088 194.5 L1120 192.8 L1152 191.9 L1184 192.1 L1216 193.4 L1248 195.5 L1280 198.3 L1312 201.2 L1344 203.8 L1376 205.6 L1408 206.1 L1440 205.0 L1472 202.0 L1504 197.1 L1536 190.6 L1568 182.7 L1600 173.9 L1632 164.9" stroke-opacity="0.07"/>
        <path d="M0 249.1 L32 250.0 L64 249.7 L96 248.1 L128 244.9 L160 240.2 L192 234.0 L224 226.7 L256 218.9 L288 211.0 L320 203.8 L352 197.8 L384 193.5 L416 191.3 L448 191.5 L480 194.1 L512 198.8 L544 205.3 L576 213.1 L608 221.5 L640 230.0 L672 238.0 L704 244.8 L736 250.2 L768 253.8 L800 255.8 L832 256.1 L864 255.1 L896 253.3 L928 251.1 L960 249.0 L992 247.4 L1024 246.7 L1056 247.0 L1088 248.4 L1120 250.8 L1152 253.7 L1184 256.8 L1216 259.6 L1248 261.6 L1280 262.3 L1312 261.4 L1344 258.6 L1376 253.9 L1408 247.4 L1440 239.6 L1472 230.8 L1504 221.7 L1536 212.9 L1568 205.1 L1600 198.8 L1632 194.5" stroke-opacity="0.09"/>
        <path d="M0 301.6 L32 296.8 L64 290.6 L96 283.3 L128 275.4 L160 267.3 L192 259.8 L224 253.5 L256 248.9 L288 246.4 L320 246.3 L352 248.4 L384 252.8 L416 259.0 L448 266.6 L480 274.9 L512 283.3 L544 291.2 L576 298.1 L608 303.5 L640 307.3 L672 309.3 L704 309.8 L736 309.0 L768 307.3 L800 305.3 L832 303.3 L864 301.9 L896 301.3 L928 301.8 L960 303.4 L992 305.9 L1024 309.0 L1056 312.3 L1088 315.3 L1120 317.5 L1152 318.5 L1184 317.7 L1216 315.1 L1248 310.6 L1280 304.2 L1312 296.4 L1344 287.7 L1376 278.5 L1408 269.6 L1440 261.5 L1472 255.0 L1504 250.4 L1536 248.1 L1568 248.0 L1600 250.2 L1632 254.3" stroke-opacity="0.11"/>
        <path d="M0 331.9 L32 323.7 L64 316.0 L96 309.4 L128 304.5 L160 301.6 L192 301.1 L224 302.9 L256 307.0 L288 312.9 L320 320.2 L352 328.4 L384 336.6 L416 344.5 L448 351.3 L480 356.8 L512 360.7 L544 362.8 L576 363.5 L608 362.8 L640 361.3 L672 359.4 L704 357.6 L736 356.3 L768 355.9 L800 356.5 L832 358.2 L864 360.9 L896 364.2 L928 367.7 L960 371.0 L992 373.4 L1024 374.6 L1056 374.1 L1088 371.7 L1120 367.3 L1152 361.1 L1184 353.4 L1216 344.6 L1248 335.3 L1280 326.3 L1312 318.1 L1344 311.3 L1376 306.4 L1408 303.7 L1440 303.3 L1472 305.2 L1504 308.9 L1536 314.3 L1568 320.5 L1600 327.2 L1632 333.7" stroke-opacity="0.13"/>
        <path d="M0 360.1 L32 356.9 L64 356.0 L96 357.5 L128 361.2 L160 366.9 L192 373.9 L224 381.9 L256 390.0 L288 397.8 L320 404.6 L352 410.2 L384 414.1 L416 416.4 L448 417.1 L480 416.6 L512 415.2 L544 413.5 L576 411.8 L608 410.6 L640 410.3 L672 411.1 L704 413.0 L736 415.8 L768 419.3 L800 423.0 L832 426.5 L864 429.2 L896 430.6 L928 430.3 L960 428.1 L992 424.0 L1024 417.9 L1056 410.3 L1088 401.5 L1120 392.2 L1152 383.0 L1184 374.7 L1216 367.6 L1248 362.5 L1280 359.4 L1312 358.7 L1344 360.2 L1376 363.7 L1408 368.7 L1440 374.8 L1472 381.2 L1504 387.5 L1536 393.2 L1568 397.8 L1600 401.1 L1632 403.1" stroke-opacity="0.07"/>
        <path d="M0 415.6 L32 420.9 L64 427.7 L96 435.5 L128 443.5 L160 451.1 L192 458.0 L224 463.5 L256 467.5 L288 469.9 L320 470.7 L352 470.3 L384 469.1 L416 467.5 L448 465.9 L480 464.9 L512 464.7 L544 465.6 L576 467.7 L608 470.7 L640 474.3 L672 478.3 L704 482.0 L736 484.9 L768 486.5 L800 486.5 L832 484.6 L864 480.6 L896 474.7 L928 467.2 L960 458.4 L992 449.1 L1024 439.9 L1056 431.3 L1088 424.0 L1120 418.6 L1152 415.3 L1184 414.2 L1216 415.4 L1248 418.6 L1280 423.3 L1312 429.1 L1344 435.3 L1376 441.5 L1408 447.0 L1440 451.5 L1472 454.8 L1504 456.7 L1536 457.4 L1568 457.2 L1600 456.3 L1632 455.4" stroke-opacity="0.09"/>
        <path d="M0 497.0 L32 504.5 L64 511.3 L96 516.9 L128 520.9 L160 523.3 L192 524.3 L224 524.0 L256 522.9 L288 521.4 L320 520.0 L352 519.1 L384 519.0 L416 520.1 L448 522.2 L480 525.4 L512 529.3 L544 533.4 L576 537.3 L608 540.5 L640 542.4 L672 542.7 L704 541.0 L736 537.2 L768 531.5 L800 524.1 L832 515.4 L864 506.1 L896 496.7 L928 488.0 L960 480.5 L992 474.8 L1024 471.2 L1056 469.8 L1088 470.7 L1120 473.5 L1152 477.9 L1184 483.5 L1216 489.5 L1248 495.5 L1280 500.9 L1312 505.3 L1344 508.5 L1376 510.4 L1408 511.1 L1440 510.8 L1472 510.0 L1504 509.0 L1536 508.4 L1568 508.5 L1600 509.7 L1632 512.1" stroke-opacity="0.11"/>
        <path d="M0 574.3 L32 576.8 L64 577.9 L96 577.7 L128 576.7 L160 575.3 L192 574.0 L224 573.2 L256 573.3 L288 574.4 L320 576.7 L352 580.0 L384 584.1 L416 588.4 L448 592.6 L480 596.0 L512 598.2 L544 598.7 L576 597.3 L608 593.7 L640 588.2 L672 580.9 L704 572.3 L736 563.0 L768 553.6 L800 544.8 L832 537.1 L864 531.1 L896 527.2 L928 525.5 L960 526.1 L992 528.6 L1024 532.7 L1056 538.0 L1088 543.8 L1120 549.6 L1152 554.8 L1184 559.1 L1216 562.3 L1248 564.1 L1280 564.8 L1312 564.5 L1344 563.6 L1376 562.7 L1408 562.0 L1440 562.1 L1472 563.3 L1504 565.9 L1536 569.7 L1568 574.8 L1600 580.7 L1632 587.0" stroke-opacity="0.13"/>
        <path d="M0 630.4 L32 629.1 L64 627.9 L96 627.2 L128 627.4 L160 628.7 L192 631.1 L224 634.6 L256 638.8 L288 643.4 L320 647.8 L352 651.5 L384 653.9 L416 654.7 L448 653.5 L480 650.2 L512 644.9 L544 637.8 L576 629.3 L608 620.0 L640 610.5 L672 601.6 L704 593.7 L736 587.5 L768 583.3 L800 581.3 L832 581.5 L864 583.7 L896 587.6 L928 592.6 L960 598.1 L992 603.7 L1024 608.8 L1056 613.0 L1088 616.1 L1120 617.9 L1152 618.5 L1184 618.2 L1216 617.3 L1248 616.3 L1280 615.6 L1312 615.7 L1344 617.0 L1376 619.5 L1408 623.4 L1440 628.6 L1472 634.6 L1504 641.2 L1536 647.6 L1568 653.4 L1600 658.0 L1632 660.8" stroke-opacity="0.07"/>
        <path d="M0 681.5 L32 682.9 L64 685.4 L96 689.0 L128 693.4 L160 698.2 L192 702.9 L224 706.8 L256 709.5 L288 710.6 L320 709.7 L352 706.7 L384 701.6 L416 694.6 L448 686.2 L480 677.0 L512 667.5 L544 658.4 L576 650.4 L608 643.9 L640 639.5 L672 637.2 L704 637.1 L736 639.0 L768 642.5 L800 647.3 L832 652.6 L864 658.0 L896 662.9 L928 667.0 L960 670.0 L992 671.7 L1024 672.3 L1056 671.9 L1088 671.0 L1120 669.9 L1152 669.2 L1184 669.3 L1216 670.6 L1248 673.1 L1280 677.1 L1312 682.3 L1344 688.5 L1376 695.2 L1408 701.9 L1440 707.9 L1472 712.8 L1504 715.9 L1536 717.0 L1568 715.8 L1600 712.3 L1632 706.7" stroke-opacity="0.09"/>
        <path d="M0 748.0 L32 753.0 L64 757.8 L96 762.1 L128 765.1 L160 766.4 L192 765.8 L224 763.1 L256 758.2 L288 751.4 L320 743.2 L352 733.9 L384 724.4 L416 715.3 L448 707.1 L480 700.5 L512 695.7 L544 693.2 L576 692.8 L608 694.3 L640 697.6 L672 702.0 L704 707.2 L736 712.4 L768 717.1 L800 721.1 L832 723.9 L864 725.6 L896 726.1 L928 725.7 L960 724.7 L992 723.6 L1024 722.9 L1056 722.9 L1088 724.1 L1120 726.7 L1152 730.7 L1184 736.0 L1216 742.3 L1248 749.2 L1280 756.1 L1312 762.4 L1344 767.5 L1376 771.0 L1408 772.4 L1440 771.5 L1472 768.3 L1504 763.0 L1536 755.9 L1568 747.5 L1600 738.4 L1632 729.4" stroke-opacity="0.11"/>
        <path d="M0 820.5 L32 822.2 L64 821.9 L96 819.4 L128 814.7 L160 808.2 L192 800.0 L224 790.9 L256 781.4 L288 772.2 L320 763.9 L352 757.0 L384 752.1 L416 749.2 L448 748.5 L480 749.8 L512 752.8 L544 756.9 L576 761.8 L608 766.8 L640 771.4 L672 775.2 L704 778.0 L736 779.5 L768 779.9 L800 779.5 L832 778.4 L864 777.3 L896 776.5 L928 776.5 L960 777.6 L992 780.2 L1024 784.2 L1056 789.6 L1088 796.0 L1120 803.0 L1152 810.1 L1184 816.7 L1216 822.2 L1248 826.0 L1280 827.7 L1312 827.2 L1344 824.3 L1376 819.3 L1408 812.4 L1440 804.2 L1472 795.3 L1504 786.2 L1536 777.8 L1568 770.4 L1600 764.7 L1632 760.7" stroke-opacity="0.13"/>
        <path d="M0 871.2 L32 864.9 L64 856.9 L96 847.9 L128 838.4 L160 829.1 L192 820.7 L224 813.6 L256 808.4 L288 805.3 L320 804.3 L352 805.3 L384 808.0 L416 811.9 L448 816.6 L480 821.4 L512 825.8 L544 829.5 L576 832.1 L608 833.5 L640 833.9 L672 833.3 L704 832.2 L736 830.9 L768 830.1 L800 830.0 L832 831.2 L864 833.7 L896 837.7 L928 843.1 L960 849.6 L992 856.8 L1024 864.1 L1056 871.0 L1088 876.7 L1120 880.8 L1152 882.9 L1184 882.7 L1216 880.2 L1248 875.5 L1280 868.9 L1312 860.8 L1344 852.1 L1376 843.1 L1408 834.7 L1440 827.3 L1472 821.3 L1504 817.2 L1536 814.9 L1568 814.4 L1600 815.4 L1632 817.5" stroke-opacity="0.07"/>
        <path d="M0 895.4 L32 886.0 L64 877.5 L96 870.3 L128 864.9 L160 861.5 L192 860.3 L224 861.0 L256 863.4 L288 867.0 L320 871.4 L352 876.0 L384 880.3 L416 883.8 L448 886.3 L480 887.6 L512 887.8 L544 887.2 L576 886.0 L608 884.7 L640 883.7 L672 883.6 L704 884.7 L736 887.2 L768 891.2 L800 896.6 L832 903.2 L864 910.5 L896 918.0 L928 925.1 L960 931.1 L992 935.6 L1024 938.0 L1056 938.2 L1088 936.0 L1120 931.6 L1152 925.2 L1184 917.4 L1216 908.8 L1248 899.9 L1280 891.5 L1312 884.1 L1344 878.1 L1376 873.8 L1408 871.3 L1440 870.6 L1472 871.4 L1504 873.3 L1536 875.9 L1568 878.5 L1600 880.8 L1632 882.4" stroke-opacity="0.09"/>
        <path d="M0 921.4 L32 917.8 L64 916.3 L96 916.7 L128 918.8 L160 922.2 L192 926.4 L224 930.7 L256 934.8 L288 938.2 L320 940.5 L352 941.7 L384 941.9 L416 941.1 L448 939.8 L480 938.4 L512 937.4 L544 937.2 L576 938.2 L608 940.6 L640 944.6 L672 950.1 L704 956.7 L736 964.2 L768 971.8 L800 979.1 L832 985.5 L864 990.2 L896 993.0 L928 993.5 L960 991.7 L992 987.6 L1024 981.5 L1056 974.0 L1088 965.5 L1120 956.7 L1152 948.3 L1184 940.9 L1216 934.8 L1248 930.4 L1280 927.8 L1312 926.9 L1344 927.5 L1376 929.2 L1408 931.6 L1440 934.0 L1472 936.2 L1504 937.6 L1536 938.0 L1568 937.4 L1600 935.8 L1632 933.4" stroke-opacity="0.11"/>
      </g>
      <g stroke="#7FE8D4" stroke-opacity=".2" stroke-width="1.2"><circle cx="1060" cy="450" r="110"/><circle cx="1060" cy="450" r="200"/><circle cx="1060" cy="450" r="300"/><circle cx="1060" cy="450" r="410"/><circle cx="1060" cy="450" r="540"/>
        <line x1="500" y1="450" x2="1620" y2="450"/><line x1="1060" y1="-110" x2="1060" y2="1010"/>
      </g>
      <g stroke="#7FE8D4" stroke-opacity=".35" stroke-width="1.5"><line x1="1594.0" y1="450.0" x2="1620.0" y2="450.0"/><line x1="1575.8" y1="588.2" x2="1589.3" y2="591.8"/><line x1="1522.5" y1="717.0" x2="1534.6" y2="724.0"/><line x1="1437.6" y1="827.6" x2="1456.0" y2="846.0"/><line x1="1327.0" y1="912.5" x2="1334.0" y2="924.6"/><line x1="1198.2" y1="965.8" x2="1201.8" y2="979.3"/><line x1="1060.0" y1="984.0" x2="1060.0" y2="1010.0"/><line x1="921.8" y1="965.8" x2="918.2" y2="979.3"/><line x1="793.0" y1="912.5" x2="786.0" y2="924.6"/><line x1="682.4" y1="827.6" x2="664.0" y2="846.0"/><line x1="597.5" y1="717.0" x2="585.4" y2="724.0"/><line x1="544.2" y1="588.2" x2="530.7" y2="591.8"/><line x1="526.0" y1="450.0" x2="500.0" y2="450.0"/><line x1="544.2" y1="311.8" x2="530.7" y2="308.2"/><line x1="597.5" y1="183.0" x2="585.4" y2="176.0"/><line x1="682.4" y1="72.4" x2="664.0" y2="54.0"/><line x1="793.0" y1="-12.5" x2="786.0" y2="-24.6"/><line x1="921.8" y1="-65.8" x2="918.2" y2="-79.3"/><line x1="1060.0" y1="-84.0" x2="1060.0" y2="-110.0"/><line x1="1198.2" y1="-65.8" x2="1201.8" y2="-79.3"/><line x1="1327.0" y1="-12.5" x2="1334.0" y2="-24.6"/><line x1="1437.6" y1="72.4" x2="1456.0" y2="54.0"/><line x1="1522.5" y1="183.0" x2="1534.6" y2="176.0"/><line x1="1575.8" y1="311.8" x2="1589.3" y2="308.2"/></g>
      <g>
        <path d="M1060 450 L1620.0 450.0 A560 560 0 0 0 1618.6 410.9 Z" fill="#7FE8D4" fill-opacity="0.315"/>
        <path d="M1060 450 L1618.6 410.9 A560 560 0 0 0 1614.6 372.1 Z" fill="#7FE8D4" fill-opacity="0.263"/>
        <path d="M1060 450 L1614.6 372.1 A560 560 0 0 0 1607.8 333.6 Z" fill="#7FE8D4" fill-opacity="0.216"/>
        <path d="M1060 450 L1607.8 333.6 A560 560 0 0 0 1598.3 295.6 Z" fill="#7FE8D4" fill-opacity="0.172"/>
        <path d="M1060 450 L1598.3 295.6 A560 560 0 0 0 1586.2 258.5 Z" fill="#7FE8D4" fill-opacity="0.132"/>
        <path d="M1060 450 L1586.2 258.5 A560 560 0 0 0 1571.6 222.2 Z" fill="#7FE8D4" fill-opacity="0.097"/>
        <path d="M1060 450 L1571.6 222.2 A560 560 0 0 0 1554.5 187.1 Z" fill="#7FE8D4" fill-opacity="0.067"/>
        <path d="M1060 450 L1554.5 187.1 A560 560 0 0 0 1534.9 153.2 Z" fill="#7FE8D4" fill-opacity="0.042"/>
        <path d="M1060 450 L1534.9 153.2 A560 560 0 0 0 1513.0 120.8 Z" fill="#7FE8D4" fill-opacity="0.024"/>
        <line x1="1060" y1="450" x2="1620.0" y2="450.0" stroke="#B6F5E8" stroke-opacity=".55" stroke-width="1.5"/>
        <animateTransform attributeName="transform" type="rotate" from="0 1060 450" to="360 1060 450" dur="10s" repeatCount="indefinite"/>
      </g>
      <g stroke="#F4D58A" stroke-opacity=".6" stroke-width="1.6" stroke-linecap="round"><path class="route-dash" d="M120 780 Q450 560 860 700"/><path class="route-dash" d="M860 700 Q1150 830 1400 640"/><path class="route-dash" d="M1400 640 Q1630 410 1330 250"/><path class="route-dash" d="M700 210 Q1000 80 1330 250"/><path class="route-dash" d="M180 330 Q420 110 700 210"/></g>
      <g><circle r="4.5" fill="#FFFEFB"><animateMotion dur="11s" begin="0.0s" repeatCount="indefinite" path="M120 780 Q450 560 860 700"/></circle><circle r="4.5" fill="#FFFEFB"><animateMotion dur="13s" begin="1.7s" repeatCount="indefinite" path="M860 700 Q1150 830 1400 640"/></circle><circle r="4.5" fill="#FFFEFB"><animateMotion dur="12s" begin="3.4s" repeatCount="indefinite" path="M1400 640 Q1630 410 1330 250"/></circle><circle r="4.5" fill="#FFFEFB"><animateMotion dur="14s" begin="5.1s" repeatCount="indefinite" path="M700 210 Q1000 80 1330 250"/></circle><circle r="4.5" fill="#FFFEFB"><animateMotion dur="10s" begin="6.8s" repeatCount="indefinite" path="M180 330 Q420 110 700 210"/></circle></g>
      <circle cx="120" cy="780" r="5" fill="#F4D58A"/><circle cx="120" cy="780" r="11" fill="none" stroke="#F4D58A" stroke-opacity=".45"/><circle cx="180" cy="330" r="5" fill="#F4D58A"/><circle cx="180" cy="330" r="11" fill="none" stroke="#F4D58A" stroke-opacity=".45"/>
      <g>
        <g>
          <circle cx="1330" cy="250" r="6" fill="#14B8A6"/>
          <circle cx="1330" cy="250" r="6" fill="none" stroke="#14B8A6" stroke-width="2">
            <animate attributeName="r" values="6;30;6" dur="3.2s" begin="0.0s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".85;0;.85" dur="3.2s" begin="0.0s" repeatCount="indefinite"/>
          </circle>
          <text x="1346" y="254" fill="#FFFEFB" fill-opacity=".85" font-size="13" font-weight="700" letter-spacing="1.8">IN TRANSIT</text>
        </g>
        <g>
          <circle cx="860" cy="700" r="6" fill="#52C47F"/>
          <circle cx="860" cy="700" r="6" fill="none" stroke="#52C47F" stroke-width="2">
            <animate attributeName="r" values="6;30;6" dur="3.2s" begin="0.8s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".85;0;.85" dur="3.2s" begin="0.8s" repeatCount="indefinite"/>
          </circle>
          <text x="876" y="704" fill="#FFFEFB" fill-opacity=".85" font-size="13" font-weight="700" letter-spacing="1.8">DELIVERED</text>
        </g>
        <g>
          <circle cx="1400" cy="640" r="6" fill="#F4B740"/>
          <circle cx="1400" cy="640" r="6" fill="none" stroke="#F4B740" stroke-width="2">
            <animate attributeName="r" values="6;30;6" dur="3.2s" begin="1.6s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".85;0;.85" dur="3.2s" begin="1.6s" repeatCount="indefinite"/>
          </circle>
          <text x="1416" y="644" fill="#FFFEFB" fill-opacity=".85" font-size="13" font-weight="700" letter-spacing="1.8">DELAYED</text>
        </g>
        <g>
          <circle cx="700" cy="210" r="6" fill="#F0705B"/>
          <circle cx="700" cy="210" r="6" fill="none" stroke="#F0705B" stroke-width="2">
            <animate attributeName="r" values="6;30;6" dur="3.2s" begin="2.4s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".85;0;.85" dur="3.2s" begin="2.4s" repeatCount="indefinite"/>
          </circle>
          <text x="716" y="214" fill="#FFFEFB" fill-opacity=".85" font-size="13" font-weight="700" letter-spacing="1.8">EXCEPTION</text>
        </g>
      </g>
    </svg>
  </div>

    <main class="login-content">

        {{-- ===== Kiri: identitas ===== --}}
        <section class="login-intro">
            <div class="login-brand">
                <x-logo-mark class="login-logo" />
                <span class="login-wordmark">Cargo<span class="brand-accent">Trace</span></span>
            </div>

            <h1 class="login-headline" aria-label="Every shipment, always in sight.">
                @foreach ($headlineLines as $line)
                    <span class="headline-line {{ $line['class'] }}" aria-hidden="true">
                        @foreach (explode(' ', $line['text']) as $word)
                            <span class="headline-word">@foreach (mb_str_split($word) as $char)<span class="wave-char" style="--i: {{ $i++ }}">{{ $char }}</span>@endforeach</span>
                        @endforeach
                    </span>
                @endforeach
            </h1>

            <p class="login-lead">One control tower for tracking, delays and exceptions, so your team acts on what matters first.</p>

            <ul class="login-points">
                <li class="login-point">
                    <span class="login-point-icon"><x-icon name="map-pin" /></span>
                    <span><strong>Live tracking timeline</strong><small>See where every shipment is, step by step.</small></span>
                </li>
                <li class="login-point">
                    <span class="login-point-icon"><x-icon name="clock" /></span>
                    <span><strong>Delay detection</strong><small>Rules you can change, with the numbers shown.</small></span>
                </li>
                <li class="login-point">
                    <span class="login-point-icon"><x-icon name="alert-triangle" /></span>
                    <span><strong>Exceptions under control</strong><small>Spot problems before customers ask.</small></span>
                </li>
            </ul>
        </section>

        {{-- ===== Kanan: formulir langsung di atas latar (tanpa kartu) ===== --}}
        <section class="login-panel">
            @yield('content')
        </section>
    </main>
</div>
</body>
</html>
