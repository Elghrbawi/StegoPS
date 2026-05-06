<?php
require_once __DIR__ . '/../config/database.php';
class ChainManager {
private Database $db;
public function __construct() { $this->db=Database::getInstance(); }
public function getAllAlgorithms(): array {
    $algos=$this->db->fetchAll("SELECT * FROM encryption_algorithms WHERE is_active=1 ORDER BY category,algo_name");
    $grouped=[];
    foreach($algos as $a){$grouped[$a['category']][]=$a;}
    return $grouped;
}
public function saveChain(int $userId, string $name, array $steps, string $desc=''): array {
    if(empty($name))return['success'=>false,'message'=>'Chain name is required.'];
    if(count($steps)===0)return['success'=>false,'message'=>'Chain must have at least one algorithm.'];
    if(count($steps)>20)return['success'=>false,'message'=>'Maximum 20 algorithms per chain.'];
    $chainId=$this->db->insert("INSERT INTO encryption_chains (user_id,chain_name,description,total_layers) VALUES (?,?,?,?)",[$userId,$name,$desc,count($steps)]);
    foreach($steps as $order=>$step){
        $algo=$this->db->fetchOne("SELECT algo_id FROM encryption_algorithms WHERE algo_slug=?",[$step['slug']]);
        if(!$algo)continue;
        $this->db->insert("INSERT INTO chain_steps (chain_id,algo_id,step_order,key_hint) VALUES (?,?,?,?)",[$chainId,$algo['algo_id'],$order+1,$step['key_hint']??null]);
    }
    return['success'=>true,'chain_id'=>$chainId,'message'=>'Chain saved successfully!'];
}
public function getUserChains(int $userId): array {
    return$this->db->fetchAll("SELECT c.*,COUNT(s.step_id) as layer_count FROM encryption_chains c LEFT JOIN chain_steps s ON c.chain_id=s.chain_id WHERE c.user_id=? GROUP BY c.chain_id ORDER BY c.created_at DESC",[$userId]);
}
public function getUserChainsWithSteps(int $userId): array {
    $chains=$this->getUserChains($userId);
    foreach($chains as &$chain){
        $chain['steps']=$this->db->fetchAll("SELECT s.*,a.algo_name,a.algo_slug,a.category FROM chain_steps s JOIN encryption_algorithms a ON s.algo_id=a.algo_id WHERE s.chain_id=? ORDER BY s.step_order ASC",[$chain['chain_id']]);
    }
    return$chains;
}
public function getChainWithSteps(int $chainId, int $userId): ?array {
    $chain=$this->db->fetchOne("SELECT * FROM encryption_chains WHERE chain_id=? AND user_id=?",[$chainId,$userId]);
    if(!$chain)return null;
    $chain['steps']=$this->db->fetchAll("SELECT s.*,a.algo_name,a.algo_slug,a.category,a.requires_key FROM chain_steps s JOIN encryption_algorithms a ON s.algo_id=a.algo_id WHERE s.chain_id=? ORDER BY s.step_order ASC",[$chainId]);
    return$chain;
}
public function deleteChain(int $chainId, int $userId): bool {
    $result=$this->db->query("DELETE FROM encryption_chains WHERE chain_id=? AND user_id=?",[$chainId,$userId]);
    return$result->rowCount()>0;
}
public function calculateStrength(array $steps): int {
    $score=0;
    $categories=array_column($steps,'category');
    $score+=count($steps)*8;
    if(in_array('symmetric',$categories))$score+=25;
    if(in_array('asymmetric',$categories))$score+=30;
    if(in_array('classical',$categories))$score+=10;
    if(in_array('custom',$categories))$score+=15;
    return min(100,$score);
}
}