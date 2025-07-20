<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChemicalCalculatorController extends Controller
{
    public function index()
    {
        // Check if we have calculator data from a report
        $calculatorData = session('calculator_data');
        
        return view('chem-calc', compact('calculatorData'));
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'pool_volume' => 'required|numeric|min:1',
            'current_ph' => 'required|numeric|min:0|max:14',
            'target_ph' => 'required|numeric|min:0|max:14',
            'current_chlorine' => 'required|numeric|min:0',
            'target_chlorine' => 'required|numeric|min:0',
            'current_alkalinity' => 'required|numeric|min:0',
            'target_alkalinity' => 'required|numeric|min:0',
            'current_calcium' => 'required|numeric|min:0',
            'target_calcium' => 'required|numeric|min:0',
            'current_cyanuric_acid' => 'required|numeric|min:0',
            'target_cyanuric_acid' => 'required|numeric|min:0',
        ]);

        $results = $this->performCalculations($request->all());

        return view('chem-calc', compact('results'));
    }

    private function performCalculations($data)
    {
        $poolVolume = $data['pool_volume'];
        $currentPh = $data['current_ph'];
        $targetPh = $data['target_ph'];
        $currentChlorine = $data['current_chlorine'];
        $targetChlorine = $data['target_chlorine'];
        $currentAlkalinity = $data['current_alkalinity'];
        $targetAlkalinity = $data['target_alkalinity'];
        $currentCalcium = $data['current_calcium'];
        $targetCalcium = $data['target_calcium'];
        $currentCyanuricAcid = $data['current_cyanuric_acid'];
        $targetCyanuricAcid = $data['target_cyanuric_acid'];

        $results = [];

        // pH adjustments
        if ($currentPh < $targetPh) {
            // Need to raise pH (add soda ash) - 1.5 lbs per 10,000 gallons per 0.1 pH increase
            $phDifference = $targetPh - $currentPh;
            $sodaAshNeeded = $phDifference * 1.5 * $poolVolume / 10000; // lbs per 10,000 gallons
            $results['soda_ash'] = round($sodaAshNeeded, 2);
        } elseif ($currentPh > $targetPh) {
            // Need to lower pH (add muriatic acid) - 1 quart per 10,000 gallons per 0.1 pH decrease
            $phDifference = $currentPh - $targetPh;
            $muriaticAcidNeeded = $phDifference * 1.0 * $poolVolume / 10000; // quarts per 10,000 gallons
            $results['muriatic_acid'] = round($muriaticAcidNeeded, 2);
        }

        // Chlorine adjustments (using 65% calcium hypochlorite)
        if ($currentChlorine < $targetChlorine) {
            $chlorineNeeded = ($targetChlorine - $currentChlorine) * 1.5 * $poolVolume / 10000; // lbs per 10,000 gallons
            $results['chlorine'] = round($chlorineNeeded, 2);
        }

        // Alkalinity adjustments
        if ($currentAlkalinity < $targetAlkalinity) {
            $alkalinityDifference = $targetAlkalinity - $currentAlkalinity;
            $bakingSodaNeeded = $alkalinityDifference * 1.4 * $poolVolume / 10000; // lbs per 10,000 gallons
            $results['baking_soda'] = round($bakingSodaNeeded, 2);
        } elseif ($currentAlkalinity > $targetAlkalinity) {
            $alkalinityDifference = $currentAlkalinity - $targetAlkalinity;
            $muriaticAcidNeeded = $alkalinityDifference * 0.8 * $poolVolume / 10000; // quarts per 10,000 gallons
            $results['muriatic_acid_alkalinity'] = round($muriaticAcidNeeded, 2);
        }

        // Calcium adjustments (corrected formula)
        if ($currentCalcium < $targetCalcium) {
            $calciumDifference = $targetCalcium - $currentCalcium;
            $calciumChlorideNeeded = $calciumDifference * 0.13 * $poolVolume / 10000; // lbs per 10,000 gallons
            $results['calcium_chloride'] = round($calciumChlorideNeeded, 2);
        }

        // Cyanuric Acid adjustments
        if ($currentCyanuricAcid < $targetCyanuricAcid) {
            $cyanuricDifference = $targetCyanuricAcid - $currentCyanuricAcid;
            $cyanuricAcidNeeded = $cyanuricDifference * 1.3 * $poolVolume / 10000; // lbs per 10,000 gallons
            $results['cyanuric_acid'] = round($cyanuricAcidNeeded, 2);
        }

        // Add water recommendations
        if ($currentCyanuricAcid > $targetCyanuricAcid) {
            $dilutionPercentage = (($currentCyanuricAcid - $targetCyanuricAcid) / $currentCyanuricAcid) * 100;
            $results['water_dilution'] = round($dilutionPercentage, 1);
        }

        return $results;
    }
}
