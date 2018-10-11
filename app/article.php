<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class article extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    // 创建文章
    public function add()
    {   

        // 检查是否有标题
        if (!rq('title'))
            return ['status' => 0, 'msg' => 'required title'];
        
        $this->title = rq('title');
        $this->content = rq('content');
        $this->sort = rq('sort');
            
        $aritcle = $this->save();
        // $article->tags()
        //     ->newPivotStatement()
        //     ->where('tag', rq('tag'))
        //     ->delete();

        // tags()->attach($this->id, ['tag' => rq('tag')]);

        // 将拿到的标签分割字符串
        $tag = true;
        if (rq('tag')){
            $tagArr = explode(",",rq('tag'));

            // 将每个标签遍历插入数据库
            foreach($tagArr as $value){

                $tag = DB::table('tags')->insert([
                    'tag' => $value,
                    'article_id' => $this->id
                ]);
            }
        }
            
        // 保存
        return ($aritcle && $tag) ? 
            suc(['id' => $this->id, 'msg' => '新增文章成功']) :
            err('db delete failed');
    }

    // 修改文章
    public function change() {
        if (!rq('id')){
            return err('id is required');
        }

        $article = $this->find(rq('id'));

        if (!$article)
            return err('找不到该文章');

        if (rq('title')){
            $article->title = rq('title');
        }
        if (rq('content')){
            $article->content = rq('content');
        }
        if (rq('sort')){
            $article->sort = rq('sort');
        }
        $tag = true;
        // if (rq('tag')){
        //     $tagArr = explode(",",rq('tag'));

        //     // 将每个标签遍历插入数据库
        //     foreach($tagArr as $value){

        //         $tag = DB::table('tags')->insert([
        //             'tag' => $value,
        //             'article_id' => rq('id')
        //         ]);
        //     }
        // }
        return ($article->save() && $tag) ? 
            suc(['msg' => '修改成功']) :
            err('db inster failed');
    }

    // 删除文章
    public function remove()
    {
        // 检查传参是否有id
        if (!rq('id'))
            return ['status' => 0, 'msg' => 'id is required'];
        
        $article = $this->find(rq('id'));
        // 检查传参id是否存在
        if (!$article)
            return ['status' => 0, 'msg' => 'id不存在'];

        // 软删除，永久删除用 forceDelete()
        return $article->delete() ?
            ['status' => 1, 'msg' => '删除成功'] : 
            ['status' => 0, 'msg' => 'db delete failed'];
    }
    
    // 恢复文章
    public function restored() {
        // 检查传参是否有id
        if (!rq('id'))
            return ['status' => 0, 'msg' => 'id is required'];

        return $this::withTrashed()->find(rq('id'))->restore() ?
            suc(['msg' => '文章已经恢复']) :
            err('db update failed');
    }

    // 查看文章
    public function read() {
        // 查看指定id
        if (rq('id'))
        {
            
            $article = $this->find(rq('id'));
            // 查找指定id是否存在
            if (!$article)
                return err('article not exists');
            // 浏览量
            $article->clicks += 1;
            $article->save();
            // 获取文章标签
            $article->tags = tag_ins()->where('article_id', rq('id'))->get(['tag']);

            return suc(['data' => $article]);
        }

        // 分页
        $limit = rq('limit') ?: 10;
        $skip = (rq('page') ? rq('page')-1 : 0) * $limit;

        // 按分类获取文章
        if (rq('sort'))
        {
            $articles = $this
                ->orderBy('created_at')
                ->where('sort', rq('sort'))
                ->limit($limit)
                ->skip($skip)
                ->get(['id', 'title', 'content', 'created_at']);
            if (!$articles)
                return err('该分类没有文章');

            foreach($articles as $item){
                $item->tags = tag_ins()->where('article_id', $item->id)->get(['tag']);
            }    
            
            return suc(['sort' => rq('sort'), 'data' => $articles]);
        }
        
        // 查看所有文章
        $list = $this
            ->orderBy('created_at')
            ->limit($limit)
            ->skip($skip)
            ->get(['id', 'title', 'content', 'created_at']);
            // ->get();
        foreach($list as $item){
            $item->tags = tag_ins()->where('article_id', $item->id)->get(['tag']);
        }  
        return suc(['data' => $list]);
    }        

    // 按年月查询文章
    public function times() {

        if (rq('year') && rq('month')) 
        {
            $articles = $this
                ->whereYear('created_at', rq('year'))
                ->whereMonth('created_at', rq('month'))
                ->get();
            if (!$articles->first())
                return err('该月份没有文章');
            return suc(['data' => $articles]);
        }
        
        // 按年查询
        if (rq('year')) 
        {
            $articles = $this
                ->whereYear('created_at', rq('year'))
                ->get();

            if (!$articles->first()){
                return err('该年份没有文章');
            }
            return suc(['data' => $articles]);
        }

        // 获取时间线，获取每个月份的文章数量
        $timeline =  $this
            ->groupBy('date')
            ->get([DB::raw('DATE_FORMAT(created_at, \'%Y年%m月\') as date'),DB::raw('COUNT(*) as value')])
            ->toArray();
        return suc(['data' => $timeline]);
    }

}
