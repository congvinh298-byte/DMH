using System;
using System.IO;
using System.Net;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Threading;
using System.Web.Script.Serialization;
using System.Collections.Generic;

namespace ViettelAutoSign
{
    class Program
    {
        static void Main(string[] args)
        {
            int port = 8080;
            HttpListener listener = new HttpListener();
            listener.Prefixes.Add(string.Format("http://localhost:{0}/sign/", port));
            
            try {
                listener.Start();
                Console.WriteLine(string.Format("LocalSignService dang chay tai: http://localhost:{0}/sign/", port));
                Console.WriteLine("Dung de tu dong ky hoa don Viettel tu trinh duyet.");
                Console.WriteLine("Nhan Ctrl+C de thoat...");

                while (true)
                {
                    HttpListenerContext context = listener.GetContext();
                    ThreadPool.QueueUserWorkItem((c) => {
                        HttpListenerContext ctx = (HttpListenerContext)c;
                        ProcessRequest(ctx);
                    }, context);
                }
            } catch (Exception ex) {
                Console.WriteLine("Loi khoi tao (Co the can chay Quyen Admin de mo port): " + ex.Message);
                Console.ReadLine();
            }
        }

        static void ProcessRequest(HttpListenerContext context)
        {
            HttpListenerRequest request = context.Request;
            HttpListenerResponse response = context.Response;

            // CORS headers
            response.AppendHeader("Access-Control-Allow-Origin", "*");
            response.AppendHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
            response.AppendHeader("Access-Control-Allow-Headers", "Content-Type");

            if (request.HttpMethod == "OPTIONS")
            {
                response.StatusCode = 200;
                response.Close();
                return;
            }

            try
            {
                if (request.HttpMethod != "POST")
                    throw new Exception("Chi ho tro POST");

                using (var reader = new StreamReader(request.InputStream, request.ContentEncoding))
                {
                    string jsonInput = reader.ReadToEnd();
                    var jss = new JavaScriptSerializer();
                    var data = jss.Deserialize<Dictionary<string, string>>(jsonInput);

                    if (!data.ContainsKey("hashString"))
                        throw new Exception("Thieu thuoc tinh hashString trong payload");

                    string hashString = data["hashString"];
                    string signature = SignHash(hashString);

                    var resData = new Dictionary<string, string>();
                    resData["signature"] = signature;
                    
                    string jsonOutput = jss.Serialize(resData);
                    byte[] buffer = Encoding.UTF8.GetBytes(jsonOutput);
                    
                    response.ContentType = "application/json";
                    response.ContentLength64 = buffer.Length;
                    response.OutputStream.Write(buffer, 0, buffer.Length);
                }
            }
            catch (Exception ex)
            {
                var jss = new JavaScriptSerializer();
                var resData = new Dictionary<string, string>();
                resData["error"] = ex.Message;
                
                string jsonOutput = jss.Serialize(resData);
                byte[] buffer = Encoding.UTF8.GetBytes(jsonOutput);
                
                response.ContentType = "application/json";
                response.StatusCode = 500;
                response.ContentLength64 = buffer.Length;
                response.OutputStream.Write(buffer, 0, buffer.Length);
            }
            finally
            {
                response.OutputStream.Close();
            }
        }

        static string SignHash(string base64Str)
        {
            if (string.IsNullOrEmpty(base64Str))
                throw new ArgumentNullException("input");

            // Open store to pick cert
            X509Store store = new X509Store(StoreName.My, StoreLocation.CurrentUser);
            store.Open(OpenFlags.ReadOnly | OpenFlags.OpenExistingOnly);
            
            X509Certificate2Collection collection = (X509Certificate2Collection)store.Certificates;
            X509Certificate2Collection fcollection = (X509Certificate2Collection)collection.Find(X509FindType.FindByTimeValid, DateTime.Now, false);
            
            // Hien thi Dialog cho nguoi dung chon chung thu (Neu can) hoac chon tu dong
            X509Certificate2Collection scollection = X509Certificate2UI.SelectFromCollection(fcollection, "Chon chung thu so", "Vui long chon chung thu so (USB Token) de ky", X509SelectionFlag.SingleSelection);
            
            if (scollection.Count == 0)
                throw new Exception("Khong co chung thu so nao duoc chon");

            X509Certificate2 certificate = scollection[0];

            if (!certificate.HasPrivateKey)
                throw new Exception("Chu ky so duoc chon khong co khoa bi mat");

            RSACryptoServiceProvider csp = (RSACryptoServiceProvider)certificate.PrivateKey;
            if (csp == null)
                throw new Exception("Khong the lay duoc RSACryptoServiceProvider tu chung thu");

            byte[] data = Convert.FromBase64String(base64Str);
            byte[] res = csp.SignHash(data, CryptoConfig.MapNameToOID("SHA1"));

            return Convert.ToBase64String(res);
        }
    }
}
