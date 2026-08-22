<?php

/**
 * Peso que corresponde a cada talla. Es UNA sola fuente: la usa la guía de
 * tallas de la tienda y la tabla de precios del admin, para que nunca digan
 * cosas distintas.
 *
 * La llave se compara en mayúsculas y sin espacios. Si una talla no está aquí,
 * se busca el campo "weight" de esa talla en el catálogo.
 */
return [

    // Tallas de bebé
    'S'    => '4–8 kg · 9–18 lb',
    'M'    => '6–11 kg · 13–24 lb',
    'L'    => '9–14 kg · 20–31 lb',
    'XL'   => '12–17 kg · 26–37 lb',
    'XXL'  => '15–21 kg · 33–46 lb',
    'XXXL' => '18–25 kg · 39–55 lb',

    // Niños grandes
    '4 A 7 AÑOS'  => '17–29 kg · 37–64 lb',
    '8 A 14 AÑOS' => '27–57 kg · 60–126 lb',

    // OJO: el rango de recién nacido no estaba en la tienda. Confirmalo con el
    // empaque y corregilo acá; mientras tanto sale en blanco a propósito.
    // 'RN' => 'hasta 4 kg · hasta 9 lb',

];
