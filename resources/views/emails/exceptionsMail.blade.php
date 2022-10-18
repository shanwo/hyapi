<body>
<table style="font-family:-apple-system, PingFangSC-Light , BlinkMacSystemFont, Segoe UI, Roboto, Ubuntu, Helvetica Neue, Helvetica, Arial, PingFang SC, Hiragino Sans GB, Microsoft YaHei UI, Microsoft YaHei, Source Han Sans CN, sans-serif;font-size:16px;color:#4a4a4a; background-color:#fff; padding:20px;">
    <tbody>
    <tr>
        <td style="font-family: Arial, sans-serif;font-size: 16px;line-height: 20px;">
            <strong>API接口请求错误</strong></td>
    </tr>
    <tr>
        <td style="padding-bottom:20px;">
            错误信息如下：
        </td>
    </tr>
    <tr>
        <td>
            <table style="border-collapse:collapse;box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1), 0 -1px 0px rgba(0, 0, 0, 0.02);width: 640px">
                <tbody>
                <tr>
                    <td style="border:1px solid #ccc;padding:10px;background-color:#eee;width: 50px">
                        错误编码
                    </td>
                    <td style="border:1px solid #ccc;padding:10px;">
                        <strong>{{$code}}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #ccc;padding:10px;background-color:#eee;">
                        错误名称
                    </td>
                    <td style="border:1px solid #ccc;padding:10px;">
                        <strong>{{$msg}}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #ccc;padding:10px;background-color:#eee;">
                        错误信息
                    </td>
                    <td style="border:1px solid #ccc;padding:10px;">
                        <strong>{{$data}}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #ccc;padding:10px;background-color:#eee;">
                        请求标识
                    </td>
                    <td style="border:1px solid #ccc;padding:10px;">
                        <strong>{{$uuid}}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #ccc;padding:10px;background-color:#eee;">
                        请求数据
                    </td>
                    <td style="border:1px solid #ccc;padding:10px;">
                        <strong>{{json_encode($request,JSON_UNESCAPED_UNICODE)}}</strong>
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:20px 0;">如遇任何问题，请直接邮件闪我团队管理人员邮件组：<a href="mailto:admin@shanwo.cc">admin@shanwo.cc</a></td>
    </tr>
    </tbody>
</table>
</body>
