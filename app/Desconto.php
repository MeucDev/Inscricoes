<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Desconto extends Model
{
    public $timestamps = false;

    public static function getDesconto($pessoa){
        $desconto = Desconto::where('cpf', $pessoa->cpf)
            ->orWhere('nome', $pessoa->cidade)->first();

        if ($desconto)
            return $desconto->perc;
        else
            return 0;
    }
}
