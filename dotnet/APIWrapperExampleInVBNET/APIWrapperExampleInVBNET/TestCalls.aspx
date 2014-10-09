<%@ Page Language="vb" AutoEventWireup="false" CodeBehind="TestCalls.aspx.vb" Inherits="APIWrapperExampleInVBNET.TestCalls" %>

<!DOCTYPE html>

<html xmlns="http://www.w3.org/1999/xhtml">
<head runat="server">
    <title></title>
</head>
<body>
    <form id="form1" runat="server">
        <div>
            <asp:Button ID="btn1" runat="server" Text="use basic" OnClick="btn1_Click" />
        </div>
        <br />
        <div>
            <asp:Button ID="btn2" runat="server" Text="use RestSharp"
                Enabled="true" OnClick="btn2_Click" />
        </div>
        <asp:DataGrid ID="grid1" runat="server"></asp:DataGrid>
    </form>
</body>
</html>
