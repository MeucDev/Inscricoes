<?php

namespace App\Http\Controllers;

use App\Pessoa;
use App\Valor;
use App\Inscricao;
use App\Evento;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\PagSeguroIntegracao;
use Illuminate\Validation\ValidationExceptionion;

class InscricoesController extends Controller
{
    public function show($id)
    {
        $inscricao = Inscricao::findOrFail($id);
        $inscricao->pessoa;
        $inscricao->presenca = true;

        foreach ($inscricao->dependentes as $dependente) {
            $dependente->pessoa;
            $dependente->presenca = true;
        }

        return $inscricao;
    }
    
    public function pessoa($id)
    {
        $inscricao = Inscricao::findOrFail($id);

        $inscricao->pessoa->ajustarDados();

        $result =  (object) $inscricao->pessoa->toArray();
        $result->valores = $inscricao->getValores();

        foreach ($inscricao->dependentes as $dependente) {
            $dependente->pessoa->ajustarDados();
            $dep = (object) $dependente->pessoa->toArray();
            $dep->valores = $dependente->getValores();
            $result->dependentes[] = $dep;
        }
        
        return response()->json($result);
    } 
    
    public function validar(Request $request, $dados, $evento){
        $this->validate($request, [
            'TIPO' => 'required',
            'cpf' => 'required|digits:11',
            'nome' => 'required',
            'nomecracha' => 'required',
            'email' => 'required|email',
            'nascimento' => 'required|date_format:d/m/Y|after:01/01/1900|before:' . date("d/m/Y"),
            'sexo' => 'required',
            'telefone' => 'required|regex:/\d{2}\s\d{8,9}/u',
            'cep' => 'required|regex:/\d{5}-\d{3}/u',
            'uf' => 'required|min:2|max:2',
            'cidade' => 'required',
            'bairro' => 'required',
            'endereco' => 'required',
            'nroend' => 'required',
            'alojamento' => 'required',
            'refeicao' => 'required',

            'dependentes.*.TIPO' => 'required',
            'dependentes.*.nome' => 'required',
            'dependentes.*.nomecracha' => 'required',
            'dependentes.*.nascimento' => 'required|date_format:d/m/Y|after:01/01/1900',
            'dependentes.*.sexo' => 'required',
            'dependentes.*.alojamento' => 'required',
            'dependentes.*.refeicao' => 'required',
        ]); 

        if (!strrpos($dados->nome, ' '))
            throw new Exception("O nome do responsável deve ser completo");

        $refeicoesLar = Inscricao::where("evento_id", $evento->id)->where("refeicao", "like", "LAR%")->count();

        $refeicoesQuiosque = Inscricao::where("evento_id", $evento->id)->where("refeicao", "like", "QUIOSQUE%")->count();

        if ($evento->limite_refeicoes && $refeicoesLar >= $evento->limite_refeicoes)
            throw new Exception("O limite para refeições no Lar Filadélfia foi atingido.");
    }

    public function criar(Request $request, $evento){
        $evento = Evento::findOrFail($evento);
        $dados = (object) json_decode($request->getContent(), true);

        $this->validar($request, $dados, $evento);
        
        $pessoa = Pessoa::atualizarCadastros($dados);

        $result = DB::transaction(function() use ($dados, $pessoa, $evento) {
            $inscricao = Inscricao::criarInscricao($pessoa, $evento->id);
            $result = (object)[];
            if (!$dados->interno)
                $result = PagSeguroIntegracao::gerarPagamento($inscricao);
            return $result;
        });        

        return response()->json($result);
    }

    public function alterar(Request $request, $id){
        $inscricao = Inscricao::findOrFail($id);
        $dados = (object) json_decode($request->getContent(), true);

        $this->validar($request, $dados, $inscricao->evento_id);
        
        $pessoa = Pessoa::atualizarCadastros($dados);

        $result = DB::transaction(function() use ($dados, $pessoa, $id) {
            $inscricao = Inscricao::alterarInscricao($id, $pessoa);
            return $result;
        });        

        return response()->json($result);
    }

    public function presenca(Request $request, $id)
    {
        $dados = (object) json_decode($request->getContent(), true);

        $inscricao = Inscricao::findOrFail($id);

        $inscricao->presencaConfirmada = $dados->presenca;

        foreach ($inscricao->dependentes as $key => $value) {
            $value->presencaConfirmada = $dados->dependentes[$key]->presenca;
            $value->equipeRefeicao = $dados->equipeRefeicao;
        }
        
        $inscricao->valorTotalPago = $dados->valorTotalPago + $dados->recebido;
        $inscricao->equipeRefeicao = $dados->equipeRefeicao;
        $inscricao->inscricaoPaga = 1;
        $inscricao->save();
    }    
}